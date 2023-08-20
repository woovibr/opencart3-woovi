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
    public function configureAppId(string $newAppId, int $storeId = 0): void
    {
        $storeId = $this->db->escape(strval($storeId));
        $newAppId = $this->db->escape($newAppId);

        $query = $this->db->query("
            SELECT `value` FROM " . DB_PREFIX . "setting
            WHERE store_id = '" . $storeId . "'
                AND `key` = 'payment_woovi_app_id'
        ");

        if (isset($query->num_rows) && ! $query->num_rows) {
            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "setting`
                SET `store_id` = '" . $storeId . "',
                    `code` = 'payment_woovi',
                    `key` = 'payment_woovi_app_id',
                    `value` = '" . $newAppId . "'
            ");

            return;
        }

        $this->db->query("
            UPDATE `" . DB_PREFIX . "setting`
            SET `value` = '" . $newAppId
                . "', `serialized` = '0'
            WHERE `code` = 'payment_woovi'
                AND `key` = 'payment_woovi_app_id'
                AND `store_id` = '" . $storeId . "'
        ");
    }
}
