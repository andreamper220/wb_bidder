<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260729140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Attribution lag, min change pct, wb status, bid_snapshots';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE campaigns ADD attribution_lag_days INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE campaigns ADD min_change_pct INT DEFAULT 3 NOT NULL');
        $this->addSql('ALTER TABLE campaigns ADD wb_status INT DEFAULT NULL');
        $this->addSql('CREATE TABLE bid_snapshots (id SERIAL NOT NULL, campaign_id INT NOT NULL, payload JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_BID_SNAPSHOTS_CAMPAIGN ON bid_snapshots (campaign_id)');
        $this->addSql('ALTER TABLE bid_snapshots ADD CONSTRAINT FK_BID_SNAPSHOTS_CAMPAIGN FOREIGN KEY (campaign_id) REFERENCES campaigns (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bid_snapshots DROP CONSTRAINT FK_BID_SNAPSHOTS_CAMPAIGN');
        $this->addSql('DROP TABLE bid_snapshots');
        $this->addSql('ALTER TABLE campaigns DROP attribution_lag_days');
        $this->addSql('ALTER TABLE campaigns DROP min_change_pct');
        $this->addSql('ALTER TABLE campaigns DROP wb_status');
    }
}
