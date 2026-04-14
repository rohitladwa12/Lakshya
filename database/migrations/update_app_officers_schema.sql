DROP TABLE IF EXISTS `app_officers`;

CREATE TABLE `app_officers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('placement_officer','internship_officer','admin') NOT NULL,
  `institution` varchar(20) DEFAULT 'GMU',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `app_officers` (`id`, `username`, `password`, `full_name`, `email`, `role`, `institution`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'placement_admin', '$2y$10$UCBZeBlJ5INm/sux48zk1.vXche6IR1OklFiCCl1/vUtaXUY/eaXm', 'Placement Officer', 'placement@gmu.ac.in', 'placement_officer', 'GMU', 1, '2026-02-05 04:52:50', '2026-02-05 04:52:50'),
(2, 'internship_admin', '$2y$10$UCBZeBlJ5INm/sux48zk1.vXche6IR1OklFiCCl1/vUtaXUY/eaXm', 'Internship Officer', 'internship@gmu.ac.in', 'internship_officer', 'GMU', 1, '2026-02-05 04:52:50', '2026-02-05 04:52:50');
