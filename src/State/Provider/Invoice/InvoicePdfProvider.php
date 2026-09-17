<?php

declare(strict_types=1);

namespace App\State\Provider\Invoice;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\DTO\InvoicePdf;
use App\Entity\Invoice;
use App\Enum\InvoiceStatus;
use App\Service\InvoicePdfGenerator;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProviderInterface<InvoicePdf>
 */
final readonly class InvoicePdfProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<Invoice> $itemProvider
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        private ProviderInterface $itemProvider,
        private InvoicePdfGenerator $invoicePdfGenerator,
    ) {
    }

    /**
     * @param array<string, mixed> $uriVariables
     * @param array<string, mixed> $context
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): InvoicePdf
    {
        $invoice = $this->itemProvider->provide($operation, $uriVariables, $context);

        if (!$invoice instanceof Invoice) {
            throw new NotFoundHttpException();
        }

        if ($invoice->getStatus() === InvoiceStatus::DRAFT) {
            throw new UnprocessableEntityHttpException('Koncept není daňový doklad, PDF má jen vystavená faktura.');
        }

        return $this->invoicePdfGenerator->generate($invoice);
    }
}
