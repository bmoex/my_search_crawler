#
# Queue table for internal handling of scheduler
# Table structure for table 'tx_mysearchcrawler_domain_model_queue'
#
CREATE TABLE tx_mysearchcrawler_domain_model_queue (
    uid int(11) NOT NULL auto_increment,
    crdate int(11) DEFAULT '0' NOT NULL,
    cruser_id int(11) DEFAULT '0' NOT NULL,
    tstamp int(11) DEFAULT '0' NOT NULL,
    identifier varchar(40) DEFAULT '' NOT NULL,
    page_url varchar(1024) DEFAULT '' NOT NULL,
    running varchar(32) DEFAULT '' NOT NULL,
    caller text,

    PRIMARY KEY (uid),
    KEY running (running),
    UNIQUE unique_hash (identifier)
);
