;
;
;
CREATE TABLE `requests` (
  `udid` varchar(40) NOT NULL,
  `productid` varchar(100) NOT NULL,
  `status` tinyint(1) NOT NULL default '0',
  `updated` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`udid`,`productid`),
  KEY `status` (`status`)
);