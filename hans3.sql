-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Lis 13, 2025 at 01:48 PM
-- Wersja serwera: 10.4.32-MariaDB
-- Wersja PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hans`
--

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `admin`
--

CREATE TABLE `admin` (
  `ID` int(11) NOT NULL,
  `admin_name` varchar(50) NOT NULL,
  `email` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`ID`, `admin_name`, `email`, `password`) VALUES
(4, 'admin', 'admin@example.com', '$2y$10$T23xkEVhdQxB/UWdleIs3uaB/fHm1jH6ea3GNYzGtTuvNoZSIfk0K'),
(5, 'admin', 'admin@example.com', '$2y$10$CBBCZFHDj7L4r27tMOexm.5yYEvCPuI31As0kpOAbyQCwZeOxIgva');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `note_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `files`
--

CREATE TABLE `files` (
  `id` int(11) NOT NULL,
  `note_id` int(11) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `notes`
--

CREATE TABLE `notes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notes`
--

INSERT INTO `notes` (`id`, `user_id`, `title`, `content`, `file_path`, `created_at`, `updated_at`) VALUES
(12, 7, 'Całki', 'Całkowanie jest działaniem odwrotnym do różniczkowania.\r\nŻeby sprawnie liczyć całki, należy wcześniej dobrze opanować liczenie pochodnych. Znaczek dx oznacza, że całkujemy funkcję f(x) po zmiennej x (i tak symbolicznie zamyka operację całkowania). Przy liczeniu prostych całek symbol dx na nic nie wpływa, ale należy go pisać ze względów formalnych. Do całkowania prostych funkcji wykorzystujemy wzory całkowe, które są również przydatne przy liczeniu całek bardziej skomplikowanych funkcji.', 'uploads/e15489de268552a0cf614fe2edc9525c8fd36ba9aeac9be6a4c7dc30c32ec49c.jpg', '2025-11-09 13:26:34', '2025-11-09 13:26:34'),
(13, 7, 'Ruch wahadłowy', 'Ruch wahadłowy to taki, który występuje w obiekcie z jednej strony na drugą, zwisający z włókna, kabla lub nici. Siły, które interweniują w tym ruchu, to połączenie siły grawitacji (pionowej, w kierunku środka Ziemi) i napięcia nici (kierunek nici). To właśnie robią zegary wahadłowe (stąd jego nazwa) lub huśtawki na placu zabaw. W idealnym wahadle ruch oscylacyjny będzie trwał wiecznie. Jednak w prawdziwym wahadle ruch kończy się z czasem z powodu tarcia z powietrzem. Myślenie o wahadle nieuchronnie wywołuje obraz zegara wahadłowego, pamięci tego starego i imponującego zegara wiejskiego domu dziadków. A może opowieść o terrorze Edgara Allana Poe, studni i wahadle, której narrację inspiruje jedna z wielu metod tortur stosowanych przez hiszpańską inkwizycję.', NULL, '2025-11-09 13:28:15', '2025-11-09 13:28:15'),
(16, 7, 'Krzysztof Kolumb', 'Kapitan wyprawy, która płynęła na trzech statkach: „Santa María”, „Niña” i „Pinta” pod flagą Kastylii w poszukiwaniu zachodniej drogi morskiej do wschodniej Azji[a]. Jako pierwsza wyprawa w historii nowożytnych odkryć geograficznych pokonała zwrotnikowy Ocean Atlantycki i 12 października 1492 dotarła do Indii Zachodnich u wybrzeży Ameryki – kontynentu nieznanego w ówczesnej Europie. Za dokonania został mianowany admirałem i pierwszym namiestnikiem hiszpańskich kolonii w Ameryce Środkowej. Organizator i kapitan czterech odkrywczych wypraw transatlantyckich z Hiszpanii do Ameryki.', 'uploads/55a17eda7da09bef2a3b8c5ef62e38664da38aa01f877693cf1487f6bc694eae.jpg', '2025-11-09 13:32:19', '2025-11-09 13:32:19'),
(17, 1, 'Test dźwięku', 'Uwaga Natala testuje dźwięk.\r\n\r\n/EDIT \r\nTrzeba poprawić, by nazwy plików się nie zmieniały na kilometrowy ciąg znaków. Może uda się lepiej odtwarzanie zrobić też.\r\n/EDIT2\r\nNuta ukradziona od mojego basisty 😈😈', 'uploads/a3752543791d0dd17c5ab19926f82e671870ee4ae0667b7b114464066bb19081.mp3', '2025-11-13 13:31:22', '2025-11-13 13:48:09');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `note_tags`
--

CREATE TABLE `note_tags` (
  `note_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `note_tags`
--

INSERT INTO `note_tags` (`note_id`, `tag_id`) VALUES
(12, 1),
(13, 2),
(16, 12),
(17, 13);

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `tags`
--

CREATE TABLE `tags` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tags`
--

INSERT INTO `tags` (`id`, `name`) VALUES
(2, 'Fizyka'),
(12, 'historia'),
(1, 'Matematyka'),
(3, 'MISS'),
(13, 'test');

-- --------------------------------------------------------

--
-- Struktura tabeli dla tabeli `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `created_at`) VALUES
(1, 'zagielek', 'junior.zaglewski@wp.pl', '$2y$10$REDVDIt3nPq1VjbKXEoit.hN4bWHqtK4KSI.GH4Hgz9w6tNjWZtOu', '2025-10-12 18:55:50'),
(2, 'benia', 'benia@wp.pl', '$2y$10$t8cx5ME2MS0y3dR0R6aJl.Yp.MRmSHoIJTLn0Fv2IlgDDX7Xa3gd6', '2025-10-16 14:37:14'),
(4, '2222', 'blabla@pl.pl', '$2y$10$5RCp5lgnnSpFxWPfoTUyfO2CvvZqjy6kscmEfbFavve1x.6a/H9mC', '2025-10-29 20:17:35'),
(7, 'benito', 'bednarek2c@onet.pl', '$2y$10$ScpwalPVB8ovdgZUcQSKvuRH4Yxacpv4JUB1URLO9z4OPYOeT.ipm', '2025-11-06 21:18:03'),
(8, 'laska', '29903@s.pm.szczecin.pl', '$2y$10$tV6uyabWoyUN4B8LdPjtPeKmaehbGTIQ4Ffjh8zpV5uIII6GT5ul6', '2025-11-07 11:14:52');

--
-- Indeksy dla zrzutów tabel
--

--
-- Indeksy dla tabeli `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`ID`);

--
-- Indeksy dla tabeli `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `note_id` (`note_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeksy dla tabeli `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `note_id` (`note_id`);

--
-- Indeksy dla tabeli `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeksy dla tabeli `note_tags`
--
ALTER TABLE `note_tags`
  ADD PRIMARY KEY (`note_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Indeksy dla tabeli `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indeksy dla tabeli `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `files`
--
ALTER TABLE `files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notes`
--
ALTER TABLE `notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`note_id`) REFERENCES `notes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `files`
--
ALTER TABLE `files`
  ADD CONSTRAINT `files_ibfk_1` FOREIGN KEY (`note_id`) REFERENCES `notes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notes`
--
ALTER TABLE `notes`
  ADD CONSTRAINT `notes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `note_tags`
--
ALTER TABLE `note_tags`
  ADD CONSTRAINT `note_tags_ibfk_1` FOREIGN KEY (`note_id`) REFERENCES `notes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `note_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
