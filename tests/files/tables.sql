CREATE TABLE `languages` (
  `id` int(11) NOT NULL,
  `code` varchar(2) NOT NULL,
  `name` varchar(40) NOT NULL,
  `order` int(2) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `languages` (`id`, `code`, `name`, `order`) VALUES
(1, 'cz', 'Čeština', 5),
(2, 'en', 'Angličtina', 10);

CREATE TABLE `translations` (
  `id` int(11) NOT NULL,
  `language` varchar(2) NOT NULL,
  `namespace` varchar(255) DEFAULT NULL,
  `original` text NOT NULL,
  `count` enum('1','2','5') DEFAULT '1',
  `translation` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `translations` (`id`, `language`, `namespace`, `original`, `count`, `translation`) VALUES
(1, 'cz', NULL, 'app.test', '1', 'test'),
(2, 'cz', NULL, 'app.test', '2', 'testy'),
(3, 'cz', NULL, 'app.test', '5', 'testů'),
(4, 'cz', 'test', 'app.test', '1', 'prasátko'),
(5, 'cz', 'test', 'app.test', '2', 'prasátka'),
(6, 'cz', 'test', 'app.test', '5', 'prasátek');

ALTER TABLE `languages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

ALTER TABLE `translations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `language` (`language`);

ALTER TABLE `languages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `translations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `translations`
  ADD CONSTRAINT `translations_ibfk_1` FOREIGN KEY (`language`) REFERENCES `languages` (`code`);