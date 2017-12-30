<?php

namespace App\Services;

use App\Contracts\SettingsServiceInterface;

class SettingsService implements SettingsServiceInterface {

    protected $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    public function createTable() {
        $sql = "CREATE TABLE settings (id INTEGER PRIMARY KEY AUTO_INCREMENT, spread decimal(8,2),sellspread decimal(8,2), max_orders int, bottom decimal(10,2),top decimal(10,2) ,size varchar(10),lifetime int, botactive tinyint(1), created_at datetime, updated_at timestamp);";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        $sql = "INSERT INTO settings SET spread= :spread, max_orders=:maxorders, top=:top, bottom = :bottom, size=:size,lifetime =:lifetime, botactive = :botactive, created_at = :createdat;";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue('spread', 0.01);
        $stmt->bindValue('maxorders', 8);
        $stmt->bindValue('top', 220.0);
        $stmt->bindValue('bottom', 218.0);
        $stmt->bindValue('size', 0.02);
        $stmt->bindValue('lifetime', 90);
        $stmt->bindValue('botactive', 0);
        $stmt->bindValue('createdat', date('Y-m-d H:i:s'));

        $stmt->execute();
    }
    
    public function getSettings() : array {
        $sql = "SELECT * FROM settings order by id desc limit 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $settings = $stmt->fetch();

        return $settings;
    }

}
