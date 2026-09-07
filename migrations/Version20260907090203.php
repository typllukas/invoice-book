<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907090203 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the invoice and invoice item tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE invoice (id BINARY(16) NOT NULL, number VARCHAR(11) DEFAULT NULL, status VARCHAR(16) NOT NULL, client_name VARCHAR(255) NOT NULL, client_address VARCHAR(255) NOT NULL, client_company_id VARCHAR(16) NOT NULL, client_vat_id VARCHAR(16) DEFAULT NULL, supplier_name VARCHAR(255) DEFAULT NULL, supplier_address VARCHAR(255) DEFAULT NULL, supplier_company_id VARCHAR(16) DEFAULT NULL, supplier_vat_id VARCHAR(16) DEFAULT NULL, supplier_bank_account VARCHAR(32) DEFAULT NULL, supplier_register_entry VARCHAR(255) DEFAULT NULL, issued_at DATE DEFAULT NULL, due_at DATE NOT NULL, tax_point_at DATE NOT NULL, paid_at DATETIME DEFAULT NULL, issued_vat_summary JSON DEFAULT NULL, note VARCHAR(1000) DEFAULT NULL, UNIQUE INDEX UNIQ_9065174496901F54 (number), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_uca1400_ai_ci`');
        $this->addSql('CREATE TABLE invoice_item (id BINARY(16) NOT NULL, description VARCHAR(255) NOT NULL, quantity NUMERIC(10, 3) NOT NULL, unit VARCHAR(16) NOT NULL, unit_price_net_in_major_units NUMERIC(12, 2) NOT NULL, vat_rate VARCHAR(2) NOT NULL, issued_net_amount_in_major_units NUMERIC(12, 2) DEFAULT NULL, invoice_id BINARY(16) NOT NULL, INDEX IDX_1DDE477B2989F1FD (invoice_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_uca1400_ai_ci`');
        $this->addSql('ALTER TABLE invoice_item ADD CONSTRAINT FK_1DDE477B2989F1FD FOREIGN KEY (invoice_id) REFERENCES invoice (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice_item DROP FOREIGN KEY FK_1DDE477B2989F1FD');
        $this->addSql('DROP TABLE invoice');
        $this->addSql('DROP TABLE invoice_item');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
