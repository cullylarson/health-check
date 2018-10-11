<?php

namespace Health;

class Db {
    private $dbh;

    public function __construct($dsn, $user, $pass) {
        $this->dbh = new \PDO($dsn, $user, $pass);
        $this->dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function getAllSites() {
        return $this->dbh
            ->query('SELECT * from sites')
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function addResult($siteId, $isUp) {
        $isUp = $isUp
            ? 1
            : 0;

        $this->dbh->beginTransaction();

        try {
            $this->dbh
                ->prepare('UPDATE sites SET lastChecked = NOW(), lastIsUp = :isUp WHERE id = :siteId')
                ->execute([
                    ':isUp' => $isUp,
                    ':siteId' => $siteId,
                ]);

            $this->dbh
                ->prepare('INSERT INTO results (siteId, isUp, created) VALUES (:siteId, :isUp, NOW())')
                ->execute([
                    ':siteId' => $siteId,
                    ':isUp' => $isUp,
                ]);

            $this->dbh->commit();

            return $this->dbh->lastInsertId();
        }
        catch(\Exception $e) {
            $this->dbh->rollback();
        }
    }
}
