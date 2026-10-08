<?php

declare(strict_types=1);

namespace App\Tests\Api\Invoice;

use App\DataFixtures\InvoiceFixtures;
use App\Entity\Invoice;
use App\Entity\InvoiceItem;
use App\Enum\InvoiceStatus;
use App\Enum\VatRate;
use App\Tests\CustomApiTestCase;
use BcMath\Number;
use DateTimeImmutable;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

use function array_column;
use function base64_encode;
use function count;
use function sort;
use function str_repeat;

/**
 * POST /api/graphql
 */
final class InvoiceGraphQlApiTest extends CustomApiTestCase
{
    use ClockSensitiveTrait;

    /**
     * @param array<string, string> $variables
     *
     * @return array<mixed>
     */
    private function sendGraphQlRequest(string $document, array $variables): array
    {
        $response = self::createClient()->request(
            'POST',
            '/api/graphql',
            [
                'json' => ['query' => $document, 'variables' => $variables],
                'headers' => ['Content-Type' => 'application/json'],
            ],
        );
        self::assertResponseStatusCodeSame(200);

        return $response->toArray();
    }

    /**
     * @param array<mixed> $result
     * @param list<int|string> $path
     *
     * @return array<mixed>
     */
    private function getArrayAtPath(array $result, array $path): array
    {
        $value = $result;
        foreach ($path as $key) {
            self::assertIsArray($value);
            self::assertArrayHasKey($key, $value);
            $value = $value[$key];
        }

        self::assertIsArray($value);

        return $value;
    }

    private function addDraftInvoiceWithOneLine(int $unitPriceNet): Invoice
    {
        $draftInvoice = new Invoice()
            ->setClientName('Test Client')
            ->setClientAddress('Test Address 1')
            ->setClientCompanyId('00000001')
            ->setDueAt(new DateTimeImmutable('2026-09-30'))
            ->setTaxPointAt(new DateTimeImmutable('2026-09-16'))
            ->addItem(
                new InvoiceItem()
                    ->setDescription('Test item')
                    ->setQuantity(new Number('1'))
                    ->setUnit('ks')
                    ->setUnitPriceNet($unitPriceNet)
                    ->setVatRate(VatRate::EXEMPT),
            );
        self::getEntityManager()->persist($draftInvoice);
        self::getEntityManager()->flush();

        return $draftInvoice;
    }

    public function testOneInvoiceComesWithItsLinesAndVatRecapitulation(): void
    {
        self::createClient();

        $issuedInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_ISSUED_UNPAID_ULID);
        self::assertNotSame([], $issuedInvoice->getItems());

        $result = $this->sendGraphQlRequest(
            'query ($id: ID!) {
                invoice(id: $id) { number items { description netAmount } vatSummary { vatRate grossAmount } }
            }',
            ['id' => '/api/invoices/' . $issuedInvoice->getId()->toBase32()],
        );

        self::assertJsonContains([
            'data' => [
                'invoice' => [
                    'number' => $issuedInvoice->getNumber(),
                    'items' => [['description' => $issuedInvoice->getItems()[0]->getDescription()]],
                ],
            ],
        ]);
        self::assertCount(
            count($issuedInvoice->getItems()),
            $this->getArrayAtPath($result, ['data', 'invoice', 'items']),
        );
        self::assertCount(
            count($issuedInvoice->getVatSummary()),
            $this->getArrayAtPath($result, ['data', 'invoice', 'vatSummary']),
        );
    }

    public function testOnlyIssueAndMarkPaidAreExposedAsMutations(): void
    {
        $result = $this->sendGraphQlRequest('{ __schema { mutationType { fields { name } } } }', []);

        $mutationFields = $this->getArrayAtPath($result, ['data', '__schema', 'mutationType', 'fields']);
        $mutationNames = array_column($mutationFields, 'name');
        sort($mutationNames);
        self::assertSame(['issueInvoice', 'markPaidInvoice'], $mutationNames);
    }

    public function testTheMutationsTakeOnlyTheInvoiceId(): void
    {
        $result = $this->sendGraphQlRequest(
            '{
                issue: __type(name: "issueInvoiceInput") { inputFields { name } }
                markPaid: __type(name: "markPaidInvoiceInput") { inputFields { name } }
            }',
            [],
        );

        foreach (['issue', 'markPaid'] as $mutationName) {
            $inputFields = $this->getArrayAtPath($result, ['data', $mutationName, 'inputFields']);
            self::assertSame(['id', 'clientMutationId'], array_column($inputFields, 'name'));
        }
    }

    public function testAMutationInTheQueryStringIsRefused(): void
    {
        $client = self::createClient();

        $unpaidInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_ISSUED_UNPAID_ULID);
        self::assertNull($unpaidInvoice->getPaidAt());

        $client->request('GET', '/api/graphql', [
            'query' => [
                'query' => 'mutation { markPaidInvoice(input: {id: "/api/invoices/'
                    . $unpaidInvoice->getId()->toBase32() . '"}) { invoice { paidAt } } }',
            ],
        ]);

        self::assertResponseStatusCodeSame(405);
        self::assertNull(self::getEntityManager()->find(Invoice::class, $unpaidInvoice->getId())?->getPaidAt());
    }

    public function testAMutationInAFormBodyIsRefused(): void
    {
        $client = self::createClient();

        $unpaidInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_ISSUED_UNPAID_ULID);
        self::assertNull($unpaidInvoice->getPaidAt());

        $client->request('POST', '/api/graphql', [
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body' => [
                'operations' => '{"query": "mutation { markPaidInvoice(input: {id: \\"/api/invoices/'
                    . $unpaidInvoice->getId()->toBase32() . '\\"}) { invoice { paidAt } } }"}',
                'map' => '{}',
            ],
        ]);

        self::assertResponseStatusCodeSame(415);
        self::assertNull(self::getEntityManager()->find(Invoice::class, $unpaidInvoice->getId())?->getPaidAt());
    }

    public function testTheListIsPagedByCursor(): void
    {
        $firstPage = $this->sendGraphQlRequest(
            '{ invoices(first: 1) { edges { node { id } } pageInfo { endCursor hasNextPage } } }',
            [],
        );
        $firstPageEdges = $this->getArrayAtPath($firstPage, ['data', 'invoices', 'edges']);
        $firstPageInfo = $this->getArrayAtPath($firstPage, ['data', 'invoices', 'pageInfo']);
        self::assertCount(1, $firstPageEdges);
        self::assertTrue($firstPageInfo['hasNextPage']);
        self::assertIsString($firstPageInfo['endCursor']);

        $secondPage = $this->sendGraphQlRequest(
            'query ($after: String!) { invoices(first: 1, after: $after) { edges { node { id } } } }',
            ['after' => $firstPageInfo['endCursor']],
        );
        $secondPageEdges = $this->getArrayAtPath($secondPage, ['data', 'invoices', 'edges']);
        self::assertCount(1, $secondPageEdges);
        self::assertNotSame($firstPageEdges, $secondPageEdges);
    }

    public function testAPageLargerThanTheMaximumIsRefused(): void
    {
        $result = $this->sendGraphQlRequest('{ invoices(first: 200) { edges { node { id } } } }', []);

        self::assertNull($this->getArrayAtPath($result, ['data'])['invoices']);
        self::assertSame(
            'first must be between 0 and 100.',
            $this->getArrayAtPath($result, ['errors', 0])['message'],
        );
    }

    public function testTheListIsNotPagedBackward(): void
    {
        $result = $this->sendGraphQlRequest('{ invoices(last: 2, before: "MTA=") { totalCount } }', []);

        self::assertArrayNotHasKey('data', $result);
        self::assertSame(
            [
                'Unknown argument "last" on field "invoices" of type "Query".',
                'Unknown argument "before" on field "invoices" of type "Query".',
            ],
            array_column($this->getArrayAtPath($result, ['errors']), 'message'),
        );
    }

    public function testANegativeCursorIsRefused(): void
    {
        $result = $this->sendGraphQlRequest(
            'query ($after: String!) { invoices(after: $after) { edges { node { id } } } }',
            ['after' => base64_encode('-10')],
        );

        self::assertSame(
            'Cursor "LTEw" is invalid.',
            $this->getArrayAtPath($result, ['errors', 0])['message'],
        );
    }

    public function testACursorBeyondTheIntegerRangeIsRefused(): void
    {
        $result = $this->sendGraphQlRequest(
            'query ($after: String!) { invoices(after: $after) { edges { node { id } } } }',
            ['after' => base64_encode('99999999999999999999')],
        );

        self::assertSame(
            'Cursor "OTk5OTk5OTk5OTk5OTk5OTk5OTk=" is invalid.',
            $this->getArrayAtPath($result, ['errors', 0])['message'],
        );
    }

    public function testTheListIsFilteredByTheStatusItReads(): void
    {
        $result = $this->sendGraphQlRequest('{ invoices(status: DRAFT) { edges { node { status } } } }', []);

        $draftEdges = $this->getArrayAtPath($result, ['data', 'invoices', 'edges']);
        self::assertNotSame([], $draftEdges);
        foreach ($draftEdges as $draftEdge) {
            self::assertSame(['node' => ['status' => 'DRAFT']], $draftEdge);
        }
    }

    public function testTheListIsFilteredByAListOfStatuses(): void
    {
        $result = $this->sendGraphQlRequest('{ invoices(status_list: [DRAFT]) { edges { node { status } } } }', []);

        $draftEdges = $this->getArrayAtPath($result, ['data', 'invoices', 'edges']);
        self::assertNotSame([], $draftEdges);
        foreach ($draftEdges as $draftEdge) {
            self::assertSame(['node' => ['status' => 'DRAFT']], $draftEdge);
        }
    }

    public function testAnUnknownStatusIsRefused(): void
    {
        $result = $this->sendGraphQlRequest('{ invoices(status: "ISSUD") { totalCount } }', []);

        self::assertArrayNotHasKey('data', $result);
        $firstError = $this->getArrayAtPath($result, ['errors', 0]);
        self::assertIsString($firstError['message']);
        self::assertStringStartsWith('Enum "InvoiceStatus" cannot represent non-enum value', $firstError['message']);
    }

    public function testASecondMutationSucceedsAfterTheFirstIsRejected(): void
    {
        self::createClient();
        self::mockTime('2026-09-26T23:30:00+02:00');

        $paidInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_ISSUED_PAID_ULID);
        self::assertNotNull($paidInvoice->getPaidAt());

        // Exclude the other 422 cause
        self::assertSame(InvoiceStatus::ISSUED, $paidInvoice->getStatus());

        $unpaidInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_ISSUED_UNPAID_ULID);
        self::assertNull($unpaidInvoice->getPaidAt());
        self::assertSame(InvoiceStatus::ISSUED, $unpaidInvoice->getStatus());

        $this->sendGraphQlRequest(
            'mutation ($paidId: ID!, $unpaidId: ID!) {
                paid: markPaidInvoice(input: {id: $paidId}) { invoice { paidAt } }
                unpaid: markPaidInvoice(input: {id: $unpaidId}) { invoice { paidAt } }
            }',
            [
                'paidId' => '/api/invoices/' . $paidInvoice->getId()->toBase32(),
                'unpaidId' => '/api/invoices/' . $unpaidInvoice->getId()->toBase32(),
            ],
        );

        self::assertJsonContains([
            'data' => [
                'paid' => null,
                'unpaid' => ['invoice' => ['paidAt' => '2026-09-26T21:30:00+00:00']],
            ],
            'errors' => [['path' => ['paid'], 'extensions' => ['status' => 422]]],
        ]);
    }

    public function testAnAmountBeyondThirtyTwoBitsReadsBackExactly(): void
    {
        self::createClient();
        $draftInvoice = $this->addDraftInvoiceWithOneLine(2_500_000_000);

        $this->sendGraphQlRequest(
            'query ($id: ID!) { invoice(id: $id) { totalNetAmount items { unitPriceNet netAmount } } }',
            ['id' => '/api/invoices/' . $draftInvoice->getId()->toBase32()],
        );

        self::assertJsonContains([
            'data' => [
                'invoice' => [
                    'totalNetAmount' => 2_500_000_000,
                    'items' => [['unitPriceNet' => 2_500_000_000, 'netAmount' => 2_500_000_000]],
                ],
            ],
        ]);
    }

    public function testIssuingADraftNumbersItInTheCurrentYear(): void
    {
        self::createClient();
        self::mockTime('2026-09-16T10:00:00+02:00');

        $draftInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_DRAFT_ULID);
        self::assertNull($draftInvoice->getNumber());

        $result = $this->sendGraphQlRequest(
            'mutation ($id: ID!) { issueInvoice(input: {id: $id}) { invoice { number } } }',
            ['id' => '/api/invoices/' . $draftInvoice->getId()->toBase32()],
        );

        $issuedNumber = $this->getArrayAtPath($result, ['data', 'issueInvoice', 'invoice'])['number'];
        self::assertIsString($issuedNumber);
        self::assertMatchesRegularExpression('/^2026-\d{6}$/D', $issuedNumber);
    }

    public function testMarkingAnIssuedInvoicePaidRecordsTheMomentInUtc(): void
    {
        self::createClient();
        self::mockTime('2026-09-26T23:30:00+02:00');

        $unpaidInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_ISSUED_UNPAID_ULID);
        self::assertNull($unpaidInvoice->getPaidAt());

        $this->sendGraphQlRequest(
            'mutation ($id: ID!) { markPaidInvoice(input: {id: $id}) { invoice { paidAt } } }',
            ['id' => '/api/invoices/' . $unpaidInvoice->getId()->toBase32()],
        );

        self::assertJsonContains([
            'data' => ['markPaidInvoice' => ['invoice' => ['paidAt' => '2026-09-26T21:30:00+00:00']]],
        ]);
    }

    public function testMarkingAPaidInvoicePaidAgainIsRejectedInsideA200(): void
    {
        self::createClient();

        $paidInvoice = self::getInvoiceEntity(InvoiceFixtures::INVOICE_ISSUED_PAID_ULID);
        self::assertNotNull($paidInvoice->getPaidAt());

        // Exclude the other 422 cause
        self::assertSame(InvoiceStatus::ISSUED, $paidInvoice->getStatus());

        $this->sendGraphQlRequest(
            'mutation ($id: ID!) { markPaidInvoice(input: {id: $id}) { invoice { paidAt } } }',
            ['id' => '/api/invoices/' . $paidInvoice->getId()->toBase32()],
        );

        self::assertJsonContains([
            'data' => ['markPaidInvoice' => null],
            'errors' => [['extensions' => ['status' => 422]]],
        ]);
    }

    public function testAQueryDeeperThanTheLimitIsRefused(): void
    {
        $document = '{ __type(name: "Invoice") { fields { type { '
            . str_repeat('ofType { ', 12) . 'name' . str_repeat(' }', 12)
            . ' } } } }';

        $result = $this->sendGraphQlRequest($document, []);

        self::assertArrayNotHasKey('data', $result);
        $firstError = $this->getArrayAtPath($result, ['errors', 0]);
        self::assertIsString($firstError['message']);
        self::assertStringStartsWith('Max query depth should be 13 but got', $firstError['message']);
    }
}
