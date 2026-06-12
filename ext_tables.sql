#
# Table structure for table 'pages'
#
CREATE TABLE pages (
    tx_chatbot_enabled tinyint(1) unsigned DEFAULT '0' NOT NULL,
    tx_chatbot_everywhere tinyint(1) unsigned DEFAULT '0' NOT NULL,
    tx_chatbot_base_url varchar(255) DEFAULT '' NOT NULL,
    tx_chatbot_model varchar(100) DEFAULT '' NOT NULL,
    tx_chatbot_api_key varchar(4096) DEFAULT '' NOT NULL,
    tx_chatbot_color_primary varchar(10) DEFAULT '' NOT NULL,
    tx_chatbot_color_background varchar(10) DEFAULT '' NOT NULL,
    tx_chatbot_color_text varchar(10) DEFAULT '' NOT NULL,
    tx_chatbot_color_title varchar(10) DEFAULT '' NOT NULL,
    tx_chatbot_position varchar(20) DEFAULT '' NOT NULL,
    tx_chatbot_start_message text,
    tx_chatbot_title varchar(255) DEFAULT '' NOT NULL,
    tx_chatbot_avatar int(11) unsigned DEFAULT '0' NOT NULL,
);
