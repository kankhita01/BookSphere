-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 18, 2025 at 08:21 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `library_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `book_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `author` varchar(100) NOT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `publisher` varchar(100) DEFAULT NULL,
  `publication_year` int(11) DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `available_quantity` int(11) NOT NULL DEFAULT 1,
  `description` text DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`book_id`, `title`, `author`, `isbn`, `publisher`, `publication_year`, `category`, `quantity`, `available_quantity`, `description`, `cover_image`, `created_at`, `updated_at`) VALUES
(1, 'Pather Panchali', 'Bibhutibhushan Bandyopadhyay', '9788170461782', 'Mitra & Ghosh', 1929, 'Novel', 5, 5, 'The first novel of the Apu Trilogy, depicting rural Bengali life.', NULL, '2025-08-17 06:25:38', '2025-08-17 06:25:38'),
(2, 'Devdas', 'Sarat Chandra Chattopadhyay', '9788122204120', 'Gurudas Chattopadhyay & Sons', 1917, 'Tragedy', 3, 2, 'A classic tragic love story that has been adapted into films multiple times.', NULL, '2025-08-17 06:25:38', '2025-08-17 06:27:47'),
(3, 'Gora', 'Rabindranath Tagore', '9788171674586', 'Visva-Bharati', 1910, 'Philosophical Novel', 4, 4, 'A novel dealing with nationalism, identity, and religion in colonial India.', NULL, '2025-08-17 06:25:38', '2025-08-17 06:25:38'),
(4, 'Shesher Kabita', 'Rabindranath Tagore', '9788171674593', 'Visva-Bharati', 1929, 'Romantic Novel', 3, 3, 'A poetic novel about unconventional love and relationships.', NULL, '2025-08-17 06:25:38', '2025-08-17 06:25:38'),
(5, 'Hajar Bachhor Dhore', 'Zahir Raihan', '9789848866153', 'Khan Brothers', 1964, 'Historical Novel', 2, 2, 'A novel spanning a thousand years of Bengali history.', NULL, '2025-08-17 06:25:38', '2025-08-17 06:25:38'),
(6, 'Lajja', 'Taslima Nasrin', '9780140240511', 'Penguin Books', 1993, 'Political Fiction', 3, 3, 'A controversial novel about religious persecution in Bangladesh.', NULL, '2025-08-17 06:25:38', '2025-08-17 06:25:38'),
(7, 'Aranyak', 'Bibhutibhushan Bandyopadhyay', '9788170461799', 'Mitra & Ghosh', 1939, 'Nature Writing', 4, 4, 'A semi-autobiographical novel about the author\'s time in the forests of Bihar.', NULL, '2025-08-17 06:25:38', '2025-08-17 06:26:28'),
(8, 'Chander Pahar', 'Bibhutibhushan Bandyopadhyay', '9788170461805', 'Mitra & Ghosh', 1937, 'Adventure', 2, 2, 'An adventure novel set in the African wilderness.', NULL, '2025-08-17 06:25:38', '2025-08-18 05:07:17'),
(9, 'Feluda Samagra', 'Satyajit Ray', '9788177565156', 'Ananda Publishers', 1965, 'Detective Fiction', 5, 5, 'Complete collection of Feluda detective stories.', NULL, '2025-08-17 06:25:38', '2025-08-17 06:25:38'),
(10, 'Professor Shonku Samagra', 'Satyajit Ray', '9788177565163', 'Ananda Publishers', 1961, 'Science Fiction', 3, 3, 'Complete collection of Professor Shonku science fiction stories.', NULL, '2025-08-17 06:25:38', '2025-08-17 06:25:38'),
(11, 'Megher Opare', 'Humayun Ahmed', '9789848866139', 'Anyaprakash', 1990, 'Romantic Novel', 4, 4, 'A popular romantic novel set in university life.', NULL, '2025-08-17 06:25:38', '2025-08-17 06:25:38'),
(12, 'Debi', 'Humayun Ahmed', '9789848866146', 'Anyaprakash', 1985, 'Supernatural', 2, 2, 'A supernatural thriller about a woman with divine powers.', NULL, '2025-08-17 06:25:38', '2025-08-17 06:25:38'),
(13, 'Kothao Keu Nei', 'Humayun Ahmed', '9789848866160', 'Anyaprakash', 1993, 'Drama', 3, 1, 'A novel about social injustice and human relationships.', NULL, '2025-08-17 06:25:38', '2025-08-18 05:56:54'),
(14, 'Nondito Noroke', 'Humayun Ahmed', '9789848866177', 'Anyaprakash', 1972, 'Psychological', 2, 1, 'A psychological novel exploring human nature.', NULL, '2025-08-17 06:25:38', '2025-08-18 06:01:48'),
(15, 'Sei Somoy', 'Sunil Gangopadhyay', '9788171674609', 'Ananda Publishers', 1981, 'Historical Fiction', 3, 3, 'A novel about 19th century Bengal and the Bengal Renaissance.', NULL, '2025-08-17 06:25:38', '2025-08-17 06:25:38');

-- --------------------------------------------------------

--
-- Table structure for table `borrowings`
--

CREATE TABLE `borrowings` (
  `borrowing_id` int(11) NOT NULL,
  `user_id` varchar(10) NOT NULL,
  `book_id` int(11) NOT NULL,
  `borrowed_date` date NOT NULL,
  `due_date` date NOT NULL,
  `return_date` date DEFAULT NULL,
  `status` enum('borrowed','returned','overdue') NOT NULL DEFAULT 'borrowed',
  `renewed_count` int(11) DEFAULT 0,
  `fine_amount` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrowings`
--

INSERT INTO `borrowings` (`borrowing_id`, `user_id`, `book_id`, `borrowed_date`, `due_date`, `return_date`, `status`, `renewed_count`, `fine_amount`, `created_at`, `updated_at`) VALUES
(1, '32300', 2, '2025-08-17', '2025-08-31', NULL, 'borrowed', 0, 0.00, '2025-08-17 06:27:47', '2025-08-17 06:27:47'),
(2, '2230', 8, '2025-08-17', '2025-08-31', '2025-08-18', 'returned', 0, 0.00, '2025-08-17 16:15:04', '2025-08-18 05:07:17'),
(3, '2230', 13, '2025-08-18', '2025-09-01', NULL, 'borrowed', 0, 0.00, '2025-08-18 05:44:22', '2025-08-18 05:44:22'),
(4, '32310', 13, '2025-08-18', '2025-09-01', NULL, 'borrowed', 0, 0.00, '2025-08-18 05:56:54', '2025-08-18 05:56:54'),
(5, '32310', 14, '2025-08-18', '2025-09-01', NULL, 'borrowed', 0, 0.00, '2025-08-18 06:01:48', '2025-08-18 06:01:48');

-- --------------------------------------------------------

--
-- Table structure for table `library_settings`
--

CREATE TABLE `library_settings` (
  `setting_id` int(11) NOT NULL,
  `setting_name` varchar(50) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `library_settings`
--

INSERT INTO `library_settings` (`setting_id`, `setting_name`, `setting_value`, `description`, `updated_at`) VALUES
(1, 'max_borrow_days', '14', 'Maximum number of days a book can be borrowed', '2025-08-16 11:30:40'),
(2, 'max_renewals', '1', 'Maximum number of times a book can be renewed', '2025-08-16 11:30:40'),
(3, 'daily_fine', '1.00', 'Daily fine amount for overdue books', '2025-08-16 11:30:40'),
(4, 'max_borrow_limit', '5', 'Maximum number of books a user can borrow at once', '2025-08-16 11:30:40'),
(5, 'library_name', 'BookSphere', 'Name of the library', '2025-08-16 17:33:48'),
(6, 'library_address', 'House 42 Road No.16, Dhanmondi, Dhaka 1209', 'Address of the library', '2025-08-16 17:41:19'),
(7, 'library_contact', 'info@booksphere.com', 'Contact email of the library', '2025-08-16 17:43:23');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` varchar(10) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('admin','user') NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password`, `user_type`, `phone`, `address`, `created_at`, `updated_at`) VALUES
('2230', 'Jarin Tasnim Kankhita', 'jarintasnimkankhita@gmail.com', '$2y$10$eYZjTby4zUuWcH3leTP0IeItWgPD6xPhvyjumFDYmXFBypXUpfwUC', 'admin', NULL, NULL, '2025-08-16 11:39:49', '2025-08-16 11:39:49'),
('32300', 'Tashrif Mahmoud', 'tashrif110@gmail.com', '$2y$10$AAb5MxgB5uBIHFDPOiaa/.p96SdPK143MyeX8l66yo0RqriIjujk6', 'user', NULL, NULL, '2025-08-16 16:21:02', '2025-08-16 16:21:02'),
('32310', 'saidur rahman protik', 'saidurprotik@gmail.com', '$2y$10$gsS2YiFrEdN4zK0IZzIe9.j6Hznfqzs.Rzlz2bnY8IKTln8LANXNq', 'user', NULL, NULL, '2025-08-18 05:47:36', '2025-08-18 05:47:36');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`book_id`),
  ADD UNIQUE KEY `isbn` (`isbn`);

--
-- Indexes for table `borrowings`
--
ALTER TABLE `borrowings`
  ADD PRIMARY KEY (`borrowing_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `library_settings`
--
ALTER TABLE `library_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_name` (`setting_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `book_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `borrowings`
--
ALTER TABLE `borrowings`
  MODIFY `borrowing_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `library_settings`
--
ALTER TABLE `library_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `borrowings`
--
ALTER TABLE `borrowings`
  ADD CONSTRAINT `borrowings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `borrowings_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`book_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
