<?php

declare(strict_types=1);

namespace MauticPlugin\MauticFactorialBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

class Version_1_0_0 extends AbstractMigration
{
    private $table = 'page_activity_tracking';

    protected function isApplicable(Schema $schema): bool
    {
        return $schema->hasTable($this->concatPrefix($this->table));
    }

    protected function up(): void
    {
        $tableName = $this->concatPrefix($this->table);

        $this->addSql("
            create table {$tableName}
            (
                id         int unsigned auto_increment primary key,
                lead_id    bigint unsigned not null,
                pageUrl    longtext        not null,
                sessionId  varchar(191)    not null,
                properties json            null,
                constraint FK_87724BA555458D
                    foreign key (lead_id) references leads (id)
                        on delete cascade
            )
                collate = utf8mb4_unicode_ci
                row_format = DYNAMIC;
            
            create index IDX_87724BA555458D
                on {$tableName} (lead_id);
            "
        );
    }
}
