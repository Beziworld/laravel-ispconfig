<?php

declare(strict_types=1);

namespace Beziworld\ISPConfig\Resources;

use Beziworld\ISPConfig\Data\Backup;

/**
 * Backups of websites (files and databases) and mailboxes.
 *
 * Backups are made by the backup cron job of each server according to the
 * backup_interval of the website or mailbox. Download, restore and delete
 * requests are queued and executed with the next cron run of the server.
 */
final class Backups extends Resource
{
    /**
     * Website backups, of one website or of all websites.
     *
     * @return list<Backup>
     */
    public function ofWebsite(?int $websiteId = null): array
    {
        return $this->mapList($this->connection->call('sites_web_domain_backup_list', ['site_id' => $websiteId]), Backup::fromArray(...));
    }

    /**
     * Mailbox backups, of one mailbox or of all mailboxes.
     *
     * @return list<Backup>
     */
    public function ofMailbox(?int $mailboxId = null): array
    {
        return $this->mapList($this->connection->call('mail_user_backup_list', ['primary_id' => $mailboxId]), Backup::fromArray(...));
    }

    /**
     * Copies a website backup into the "backup" folder of the website for download.
     */
    public function download(int $backupId): bool
    {
        return $this->confirmed('sites_web_domain_backup', ['primary_id' => $backupId, 'action_type' => 'backup_download']);
    }

    /**
     * Restores the files or the database of a website backup.
     */
    public function restore(int $backupId): bool
    {
        return $this->confirmed('sites_web_domain_backup', ['primary_id' => $backupId, 'action_type' => 'backup_restore']);
    }

    public function delete(int $backupId): bool
    {
        return $this->confirmed('sites_web_domain_backup', ['primary_id' => $backupId, 'action_type' => 'backup_delete']);
    }

    /**
     * Restores the mail of a mailbox backup into the mailbox.
     */
    public function restoreMailbox(int $backupId): bool
    {
        return $this->confirmed('mail_user_backup', ['primary_id' => $backupId, 'action_type' => 'backup_restore_mail']);
    }

    public function deleteMailbox(int $backupId): bool
    {
        return $this->confirmed('mail_user_backup', ['primary_id' => $backupId, 'action_type' => 'backup_delete_mail']);
    }
}
