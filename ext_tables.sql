CREATE TABLE pages (
    tx_abtest_variant int(11) DEFAULT '0' NOT NULL,
    tx_abtest_cookie_time int(11) DEFAULT 604800 NOT NULL,
    tx_abtest_counter int(11) DEFAULT '0' NOT NULL,

    tx_abtest_matomo_experiment_id varchar(255) DEFAULT '' NOT NULL,
    tx_abtest_matomo_variant_id varchar(255) DEFAULT '' NOT NULL,
);
