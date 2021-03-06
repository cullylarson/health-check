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
            ->query('SELECT * from sites ORDER BY name ASC')
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function addResult($siteId, $isUp, $responseTime, $status, $error) {
        $isUp = $isUp
            ? 1
            : 0;

        try {
            $this->dbh->beginTransaction();

            $this->dbh
                ->prepare('UPDATE sites SET lastChecked = NOW(), lastIsUp = :lastIsUp WHERE id = :siteId')
                ->execute([
                    ':lastIsUp' => $isUp,
                    ':siteId' => $siteId,
                ]);

            $this->dbh
                ->prepare('INSERT INTO results (siteId, isUp, responseTime, status, error, created) VALUES (:siteId, :isUp, :responseTime, :status, :error, NOW())')
                ->execute([
                    ':siteId' => $siteId,
                    ':isUp' => $isUp,
                    ':responseTime' => $responseTime,
                    ':status' => $status,
                    ':error' => $error,
                ]);

            $this->dbh->commit();

            return $this->dbh->lastInsertId();
        }
        catch(\Exception $e) {
            $this->dbh->rollback();
        }
    }

    public function getResults($siteId, $limit = 10) {
        $limit = (int) $limit;

        $sth = $this->dbh
            ->prepare("SELECT * from results WHERE siteId = :siteId ORDER BY created DESC LIMIT {$limit}");

        $sth->execute([
            ':siteId' => $siteId,
        ]);

        return $sth->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getLastDownResult($siteId) {
        $sth = $this->dbh
            ->prepare("SELECT * from results WHERE siteId = :siteId AND isUp = FALSE ORDER BY created DESC LIMIT 1");

        $sth->execute([
            ':siteId' => $siteId,
        ]);

        return $sth->fetch(\PDO::FETCH_ASSOC);
    }
}
