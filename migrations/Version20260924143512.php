<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924143512 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the invoice number series table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE invoice_number_series (id BINARY(16) NOT NULL, year INT NOT NULL, last_sequence INT NOT NULL, UNIQUE INDEX UNIQ_F8C37026BB827337 (year), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_uca1400_ai_ci`');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE invoice_number_series');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
