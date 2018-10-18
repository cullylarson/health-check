CREATE TABLE sites (
    id                  INT UNSIGNED    NOT NULL    AUTO_INCREMENT,
    url                 TEXT,
    name                TEXT,
    description         TEXT,
    lastIsUp            BOOLEAN         NOT NULL    DEFAULT TRUE,
    lastChecked         DATETIME,
    created             DATETIME,
    modified            DATETIME,
    PRIMARY KEY (id)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE results (
    id                  INT UNSIGNED    NOT NULL    AUTO_INCREMENT,
    siteId              INT UNSIGNED    NOT NULL,
    isUp                BOOLEAN,
    responseTime        DECIMAL(9,6),
    status              SMALLINT UNSIGNED,
    error               TEXT,
    created             DATETIME,
    PRIMARY KEY (id),
    FOREIGN KEY (siteId) REFERENCES sites (id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_general_ci;
