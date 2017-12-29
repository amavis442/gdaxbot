<?php

namespace App\Services;

use App\Contracts\SettingsServiceInterface;

class SettingsService implements SettingsServiceInterface {

    protected $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function getSettings() : array {
        $sql = "SELECT * FROM settings order by id desc limit 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $settings = $stmt->fetch();

        return $settings;
    }

}
