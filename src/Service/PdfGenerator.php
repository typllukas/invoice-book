<?php

declare(strict_types=1);

namespace App\Service;

use Nucleos\DompdfBundle\Wrapper\DompdfWrapperInterface;
use Twig\Environment;

final readonly class PdfGenerator
{
    private const array DOMPDF_OPTIONS = ['defaultPaperSize' => 'A4'];

    public function __construct(
        private DompdfWrapperInterface $dompdfWrapper,
        private Environment $twig,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function generate(string $template, array $context): string
    {
        return $this->dompdfWrapper->getPdf($this->twig->render($template, $context), self::DOMPDF_OPTIONS);
    }
}
