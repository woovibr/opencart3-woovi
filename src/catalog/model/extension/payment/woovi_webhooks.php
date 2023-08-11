<?php

/**
 * Called by webhook configuration.
 * 
 * @property DB $db
 */
class ModelExtensionPaymentWooviWebhooks extends Model
{
    /**
     * Configure new App ID.
     */
    public function configureAppId(string $newAppId): void
    {
        $this->db->query("
            UPDATE `" . DB_PREFIX . "setting`
            SET `value` = '" . $this->db->escape($newAppId)
                . "', `serialized` = '0'
            WHERE `code` = 'payment_woovi'
                AND `key` = 'payment_woovi_app_id'
                AND `store_id` = '0'
        ");
    }
}