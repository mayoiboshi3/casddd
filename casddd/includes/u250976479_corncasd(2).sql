-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Oct 07, 2026 at 05:45 AM
-- Server version: 11.8.9-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u250976479_corncasd`
--

-- --------------------------------------------------------

--
-- Table structure for table `barangays`
--

CREATE TABLE `barangays` (
  `id` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `svg_path` text DEFAULT NULL,
  `fill_color` varchar(10) DEFAULT '#06402B',
  `report_issue` text DEFAULT NULL,
  `report_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barangays`
--

INSERT INTO `barangays` (`id`, `name`, `svg_path`, `fill_color`, `report_issue`, `report_date`, `created_at`) VALUES
('1', 'Aplaya', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('10', 'Barangay 5', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('11', 'Barangay 6', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('12', 'Barangay 7', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('13', 'Batino', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('14', 'Bubuyan', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('15', 'Bucal', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('16', 'Bunggo', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('17', 'Burol', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('18', 'Camaligan', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('19', 'Canlubang', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('2', 'Bagong Kalsada', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('20', 'Halang', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('21', 'Hornalan', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('22', 'Kay-Anlog', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('23', 'La Mesa', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('24', 'Laguerta', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('25', 'Lawa', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('26', 'Lecheria', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('27', 'Lingga', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('28', 'Looc', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('29', 'Mabato', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('3', 'Bañadero', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('30', 'Majada Labas', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('31', 'Makiling', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('32', 'Mapagong', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('33', 'Masili', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('34', 'Maunong', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('35', 'Mayapa', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('36', 'Milagrosa', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('37', 'Paciano Rizal', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('38', 'Palingon', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('39', 'Palo-Alto', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('4', 'Banlic', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('40', 'Pansol', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('41', 'Parian', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('42', 'Prinza', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('43', 'Punta', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('44', 'Puting Lupa', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('45', 'Real', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('46', 'Saimsim', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('47', 'Sampiruhan', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('48', 'San Cristobal', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('49', 'San Jose', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('5', 'Barandal', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('50', 'San Juan', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('51', 'Sirang Lupa', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('52', 'Sucol', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('53', 'Turbina', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('54', 'Ulango', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('6', 'Barangay 1', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('7', 'Barangay 2', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('8', 'Barangay 3', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58'),
('9', 'Barangay 4', NULL, '#06402B', NULL, NULL, '2026-04-05 07:21:58');

-- --------------------------------------------------------

--
-- Table structure for table `case_messages`
--

CREATE TABLE `case_messages` (
  `message_id` int(11) NOT NULL,
  `case_id` int(11) NOT NULL,
  `sender` enum('staff','farmer') NOT NULL,
  `message_text` text NOT NULL,
  `message_type` varchar(20) NOT NULL DEFAULT 'recommendation',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `case_messages`
--

INSERT INTO `case_messages` (`message_id`, `case_id`, `sender`, `message_text`, `message_type`, `created_at`) VALUES
(69, 117, 'staff', 'Super shy', 'recommendation', '2026-09-13 15:23:28'),
(70, 71, 'staff', 'SUPER WOW', 'recommendation', '2026-09-13 15:24:09'),
(71, 139, 'staff', 'hi', 'recommendation', '2026-10-04 06:14:06'),
(72, 139, 'staff', 'test 1', 'recommendation', '2026-10-06 07:18:04'),
(73, 152, 'staff', 'Natukoy na Sakit:\n\n🌽 Potassium Deficiency\nIto ay ang kakulangan sa nutrisyong Potasyo na kailangan ng mais para sa paglaban sa sakit, lakas ng estruktura ng katawan, at maayos na sirkulasyon ng tubig. Ang kakulangang ito ay makikita sa pamamagitan ng paninilaw at unti-unting pagkatuyo na mukhang sunog sa mga gilid ng dahon (leaf margins), na palaging nagsisimula sa mga ibabang dahon patungo sa itaas habang ang gitnang bahagi ng dahon ay nananatiling berde. Nagiging sanhi rin ito ng madalas na pagtumba ng puno (lodging) dahil sa mahinang katawan, at nagreresulta sa maliliit na puso na may kulang-kulang o bansot na butil sa dulo nito.\n\nObserbasyon sa Bukid: hi', 'recommendation', '2026-10-06 10:21:29'),
(74, 152, 'staff', 'Ito ang dapat mong gawin:\r\nMaglagay agad ng Muriate of Potash (0-0-60) sa pamamagitan ng side-dressing o top-dressing sa mga apektadong hanay ng mais upang patigasin ang katawan at maiwasan ang pagtumba.', 'recommendation', '2026-10-06 10:21:29'),
(75, 152, 'staff', 'FIELD INSPECTION SCHEDULED\r\nA field inspection has been scheduled for your farm on Tuesday, October 13, 2026 at 9:30 AM. Please be available on-site at that time.', 'recommendation', '2026-10-06 10:25:10'),
(76, 152, 'staff', 'ewan ko sayo bai', 'recommendation', '2026-10-06 10:30:37'),
(77, 75, 'staff', 'Natukoy na Sakit:\n\n🌽 Gray Leaf Spot\nSakit sa dahon na dulot ng amag (Cercospora zeae-maydis) na lubhang mapanganib sa mga lugar na may mataas na kahalumigmigan at mainit na panahon. Ipinapakita nito ang sarili sa pamamagitan ng maliliit at may-kulay na batik na habang tumatagal ay nagiging hugis parihaba (rectangular) dahil nakakulong ang paglaki nito sa pagitan ng mga litid o ugat ng dahon. Ang mga sugat na ito ay may kulay abong-kayumanggi na mistulang mga sunog na parihaba sa ibabaw ng dahon. Kapag ang mga parihabang sugat na ito ay lumaki at nagtagpo, nagiging sanhi ito ng malawakang pagkatuyo ng buong dahon.\n\nObserbasyon sa Bukid: Walang detalyadong obserbasyon.', 'recommendation', '2026-10-06 22:58:29'),
(78, 75, 'staff', 'Ito ang dapat mong gawin:\r\nMag-aplay agad ng fungicide (gaya ng strobilurin o triazole) kapag ang mga parihabang sugat ay nagsisimulang lumitaw sa mga dahon malapit sa puso ng mais baho mag-pollen.', 'recommendation', '2026-10-06 22:58:29'),
(79, 152, 'staff', 'panget ka', 'recommendation', '2026-10-07 04:22:37'),
(80, 152, 'staff', 'sheesh', 'recommendation', '2026-10-07 04:28:44'),
(81, 152, 'staff', 'woww super wow', 'recommendation', '2026-10-07 04:29:07'),
(82, 152, 'staff', 'wow', 'recommendation', '2026-10-07 04:29:25'),
(83, 152, 'staff', 'wow', 'recommendation', '2026-10-07 04:30:54'),
(84, 152, 'staff', 'wew', 'recommendation', '2026-10-07 04:32:12'),
(85, 152, 'staff', 'wew', 'recommendation', '2026-10-07 04:32:20'),
(86, 152, 'staff', 'wew', 'recommendation', '2026-10-07 04:32:21'),
(87, 152, 'staff', 'ito oh', 'recommendation', '2026-10-07 04:33:41'),
(88, 152, 'staff', 'wat happened bro', 'recommendation', '2026-10-07 04:34:32'),
(89, 152, 'staff', 'idk', 'recommendation', '2026-10-07 04:35:04'),
(90, 152, 'staff', 'Natukoy na Sakit:\n\n🌽 Potassium Deficiency\nIto ay ang kakulangan sa nutrisyong Potasyo na kailangan ng mais para sa paglaban sa sakit, lakas ng estruktura ng katawan, at maayos na sirkulasyon ng tubig. Ang kakulangang ito ay makikita sa pamamagitan ng paninilaw at unti-unting pagkatuyo na mukhang sunog sa mga gilid ng dahon (leaf margins), na palaging nagsisimula sa mga ibabang dahon patungo sa itaas habang ang gitnang bahagi ng dahon ay nananatiling berde. Nagiging sanhi rin ito ng madalas na pagtumba ng puno (lodging) dahil sa mahinang katawan, at nagreresulta sa maliliit na puso na may kulang-kulang o bansot na butil sa dulo nito.\n\nObserbasyon sa Bukid: hi', 'recommendation', '2026-10-07 04:35:13'),
(91, 152, 'staff', 'Ito ang dapat mong gawin:\nSiguraduhing maayos ang supply ng tubig sa lupa dahil ang potasyo ay nangangailangan ng sapat na moisture upang epektibong maihatid ng halaman mula sa ugat patungo sa mga dahon at puso nito.', 'recommendation', '2026-10-07 04:35:13'),
(92, 152, 'staff', 'Natukoy na Sakit:\n\n🌽 Potassium Deficiency\nIto ay ang kakulangan sa nutrisyong Potasyo na kailangan ng mais para sa paglaban sa sakit, lakas ng estruktura ng katawan, at maayos na sirkulasyon ng tubig. Ang kakulangang ito ay makikita sa pamamagitan ng paninilaw at unti-unting pagkatuyo na mukhang sunog sa mga gilid ng dahon (leaf margins), na palaging nagsisimula sa mga ibabang dahon patungo sa itaas habang ang gitnang bahagi ng dahon ay nananatiling berde. Nagiging sanhi rin ito ng madalas na pagtumba ng puno (lodging) dahil sa mahinang katawan, at nagreresulta sa maliliit na puso na may kulang-kulang o bansot na butil sa dulo nito.\n\nObserbasyon sa Bukid: hi', 'recommendation', '2026-10-07 04:36:44'),
(93, 152, 'staff', 'ewwan', 'recommendation', '2026-10-07 04:36:44'),
(94, 152, 'staff', 'tapos gumagana na', 'recommendation', '2026-10-07 04:36:52'),
(95, 152, 'staff', 'wtf', 'recommendation', '2026-10-07 04:37:03'),
(96, 152, 'staff', 'luh', 'recommendation', '2026-10-07 04:39:32'),
(97, 152, 'staff', 'what if wag', 'recommendation', '2026-10-07 04:40:04'),
(98, 152, 'staff', 'luh', 'recommendation', '2026-10-07 04:47:53'),
(99, 152, 'staff', 'wew', 'recommendation', '2026-10-07 04:50:14'),
(100, 152, 'staff', 'haluh', 'recommendation', '2026-10-07 05:00:31'),
(101, 152, 'staff', 'ayaw ko pongi', 'recommendation', '2026-10-07 05:03:14'),
(102, 152, 'staff', 'what the facku', 'recommendation', '2026-10-07 05:25:25'),
(103, 152, 'staff', 'wattt', 'recommendation', '2026-10-07 05:31:30'),
(104, 152, 'staff', 'wow', 'recommendation', '2026-10-07 05:38:19'),
(105, 152, 'staff', '1234', 'recommendation', '2026-10-07 05:39:08'),
(106, 152, 'staff', 'sheesh', 'recommendation', '2026-10-07 05:40:50'),
(107, 141, 'staff', 'dafuq', 'recommendation', '2026-10-07 05:43:18');

-- --------------------------------------------------------

--
-- Table structure for table `corn_stages`
--

CREATE TABLE `corn_stages` (
  `id` int(11) NOT NULL,
  `stage_name` varchar(50) NOT NULL,
  `stage_level` varchar(10) NOT NULL,
  `min_days_white` int(11) DEFAULT NULL,
  `max_days_white` int(11) DEFAULT NULL,
  `min_days_yellow` int(11) DEFAULT NULL,
  `max_days_yellow` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_reproductive` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `corn_stages`
--

INSERT INTO `corn_stages` (`id`, `stage_name`, `stage_level`, `min_days_white`, `max_days_white`, `min_days_yellow`, `max_days_yellow`, `description`, `is_reproductive`) VALUES
(1, 'Emergence', 'VE', 4, 6, 5, 8, 'Seedling breaks soil surface; coleoptile visible', 0),
(2, '1st Leaf', 'V1', 7, 9, 9, 11, 'First leaf collar fully visible', 0),
(3, '2nd Leaf', 'V2', 10, 12, 12, 14, 'Second leaf collar visible', 0),
(4, '3rd Leaf', 'V3', 13, 15, 15, 18, 'Third leaf collar visible', 0),
(5, '4th Leaf', 'V4', 16, 18, 19, 22, 'Fourth leaf collar visible', 0),
(6, '5th Leaf', 'V5', 19, 21, 23, 26, 'Fifth leaf collar visible', 0),
(7, '6th Leaf', 'V6', 22, 25, 27, 30, 'Sixth leaf collar visible; nodal roots established', 0),
(8, '7th Leaf', 'V7', 26, 28, 31, 34, 'Rapid stem growth begins', 0),
(9, '8th Leaf', 'V8', 29, 32, 35, 38, 'Ear shoot developing internally', 0),
(10, '9th Leaf', 'V9', 33, 35, 39, 42, 'Nutrient demand increases', 0),
(11, '10th Leaf', 'V10', 36, 38, 43, 46, 'Plant height increases rapidly', 0),
(12, '11th Leaf', 'V11', 39, 41, 47, 50, 'Pre-tassel formation begins', 0),
(13, '12th Leaf', 'V12', 42, 45, 51, 55, 'Near reproductive transition', 0),
(14, 'Tasseling', 'VT', 45, 55, 55, 65, 'Tassel fully visible; vegetative growth ends', 0),
(15, 'Silking', 'R1', 50, 60, 60, 70, 'Silks emerge; pollination starts', 1),
(16, 'Blister', 'R2', 60, 65, 70, 75, 'Kernels watery; small size', 1),
(17, 'Milk', 'R3', 65, 75, 75, 90, 'Kernels filled with milky fluid', 1),
(18, 'Dough', 'R4', 75, 85, 90, 100, 'Kernels thickening, dough-like', 1),
(19, 'Dent', 'R5', 85, 95, 100, 110, 'Dent forms on kernel; starch hardening', 1),
(20, 'Physiological Maturity', 'R6', 90, 110, 100, 120, 'Black layer forms; kernels ready for harvest', 1);

-- --------------------------------------------------------

--
-- Table structure for table `diseases`
--

CREATE TABLE `diseases` (
  `disease_id` int(11) NOT NULL,
  `disease_name` varchar(100) NOT NULL,
  `scientific_name` varchar(150) DEFAULT NULL,
  `disease_type` varchar(50) DEFAULT NULL,
  `severity_level` varchar(20) DEFAULT 'medium',
  `description` text DEFAULT NULL,
  `recommended_treatment` text DEFAULT NULL,
  `prevention_measures` text DEFAULT NULL,
  `affected_growth_stages` varchar(255) DEFAULT NULL,
  `mortality_rate` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `diseases`
--

INSERT INTO `diseases` (`disease_id`, `disease_name`, `scientific_name`, `disease_type`, `severity_level`, `description`, `recommended_treatment`, `prevention_measures`, `affected_growth_stages`, `mortality_rate`, `created_at`) VALUES
(1, 'Common Rust', NULL, 'Fungal', 'high', 'Ito ay isang sakit sa dahon na dulot ng amag (Puccinia sorghi) na karaniwang umaatake kapag malamig at basa ang panahon. Ang sakit na ito ay madaling makilala sa pamamagitan ng pagkakaroon ng maliliit, bilog o pahabang paltos (pustules) na kulay tsokolate o pulang-kayumanggi sa magkabilang panig ng dahon. Kapag hinawakan ang mga paltos na ito, naglalabas ito ng tila pulbos na kulay kalawang. Sa mga malalalang impeksyon, ang mga paltos ay kumakalat nang husto na nagiging sanhi ng pagkatuyo at maagang pagkamatay ng mga apektadong dahon.', 'Mag-spray agad ng angkop at rehistradong fungicide (gaya ng azoxystrobin o tebuconazole) sa mga dahon kapag nakitaan ng mga unang paltos.\r\nAgad na tanggalin at sirain ang mga dahong may malalang impeksyon kung nagsisimula pa lamang ang sakit sa maliit na bahagi ng taniman upang hindi na tangayin ng hangin ang mga spores nito.', 'Gumamit ng resistant na binhi at iwasan ang sobrang siksik na tanim.', 'V4-R6', 45, '2026-04-03 06:24:42'),
(2, 'Northern Leaf Blight', NULL, 'Fungal', 'critical', 'Isa itong mapaminsalang sakit sa dahon na dulot ng amag (Exserohilum turcicum) na maaaring magdulot ng malaking bawas sa ani kapag umatake bago o habang namamalisbis ang mais. Ang pangunahing palatandaan nito ay ang mga natatanging pahabang sugat (lesions) sa dahon na hugis tabako o bangka, at may kulay abong-berde o kayumanggi. Ang mga sugat na ito ay karaniwang nagsisimula muna sa mga ibabang bahagi ng dahon at unti-unting umaakyat pataas. Kapag pumasok ang basang panahon, mapapansin ang pagdami ng kulay itim o madilim na amag na tumutubo mismo sa ibabaw ng mga sugat na ito.', 'Mag-spray ng angkop na systemic fungicide kapag nakita ang mga sugat bago o sa mismong panahon ng pag-umbok ng bulaklak (tasseling) upang mapigilan ang pag-akyat ng amag sa mga itaas na dahon na kritikal sa pagpapalaki ng butil.\r\nKung tapos na ang ani at umatake ang sakit, siguraduhing araruhing mabuti at ibaon nang malalim o sunugin ang lahat ng natirang bahagi ng halaman upang tuluyang mapuksa ang natitirang amag sa lupa at hindi na makahawa sa susunod na siklo.', 'Magtanim ng resistant varieties at mag-crop rotation.', 'V8-R5', 65, '2026-04-03 06:24:42'),
(3, 'Gray Leaf Spot', NULL, 'Fungal', 'medium', 'Sakit sa dahon na dulot ng amag (Cercospora zeae-maydis) na lubhang mapanganib sa mga lugar na may mataas na kahalumigmigan at mainit na panahon. Ipinapakita nito ang sarili sa pamamagitan ng maliliit at may-kulay na batik na habang tumatagal ay nagiging hugis parihaba (rectangular) dahil nakakulong ang paglaki nito sa pagitan ng mga litid o ugat ng dahon. Ang mga sugat na ito ay may kulay abong-kayumanggi na mistulang mga sunog na parihaba sa ibabaw ng dahon. Kapag ang mga parihabang sugat na ito ay lumaki at nagtagpo, nagiging sanhi ito ng malawakang pagkatuyo ng buong dahon.', 'Mag-aplay agad ng fungicide (gaya ng strobilurin o triazole) kapag ang mga parihabang sugat ay nagsisimulang lumitaw sa mga dahon malapit sa puso ng mais baho mag-pollen.\r\nSiguraduhing sunugin o ibaon nang malalim ang mga natirang basura ng pananim pagkatapos ng gapasan upang patayin ang amag na natutulog sa mga natirang tuod.', 'Mag-crop rotation at panatilihin ang tamang pagitan ng tanim para sa maayos na airflow.', 'R1-R6', 30, '2026-04-03 06:24:42'),
(4, 'Healthy Corn', NULL, 'Healthy', 'low', 'Malusog ang halaman ng mais at walang palatandaan ng sakit.', 'Walang kailangang gamutin.', 'Panatilihin ang tamang pagdidilig, abono, at regular na pag-monitor.', 'V2-R6', 0, '2026-04-03 06:24:42'),
(999, 'Other / Unidentified', NULL, NULL, 'medium', 'Custom field for diseases not listed in the database', NULL, NULL, NULL, 0, '2026-04-06 13:29:06'),
(5, 'Banded Leaf at Sheath Blight', NULL, 'Fungal', 'high', 'Ito ay ang pagkatuyo ng katawan at dahon ng mais dulot ng amag na Rhizoctonia solani na karaniwang umaatake sa huling bahagi ng pagdadahon hanggang sa pagbubuhok. Ang mga sintomas nito ay gumagapang na panunuyo na karaniwang nagsisimula sa ibabang bahagi ng lapak ng katawan ng mais hanggang sa umakyat sa dahon at bunga, na nag-iiwan ng itsurang parang nabanglian ng mainit na tubig o may an-an.', 'Agad na bunutin, alisin, ibaon, o sunugin ang mga tanim na nagpapakita ng malalang sintomas upang hindi na mahawaan ang mga katabing puno sa pamamagitan ng dikit-dikit na dahon.\r\nGumamit ng kaibigang amag na trichoderma bilang seed treatment o ihalo sa lupa kung Open Pollinated Variety (OPV) ang itatanim upang labanan at patayin ang amag ng sheath blight.\r\nLaliman ang pagbubungkal at pag-aararo ng lupa pagkatapos ng ani upang ibaon at mamatay ang mga nakikitang buto (sclerotia) ng amag.\r\nIwasang magtanim ng mga halamang inaatake rin ng amag na ito gaya ng palay, kamatis, legumbre, cucurbits, crucifers, at karot sa mga susunod na siklo.', 'Laliman ang pagbubungkal ng lupa, iwasan ang dikit-dikit na pagtatanim, at huwag magtanim ng mga halamang inaatake rin nito gaya ng palay o kamatis.', NULL, 0, '2026-07-19 07:16:22'),
(6, 'Philippine Corn Downy Mildew', NULL, 'Fungal', 'critical', 'Isang mapanganib na sakit na pumuputi ng dahon dulot ng amag, na maaaring makabawas ng 40% hanggang 100% ng kabuuang ani, lalo na kapag tinamaan ang mais na kulang sa apat na linggo ang gulang. Ang mga sintomas nito ay ang pagkakaroon ng pahabang puti o dilaw na linya na nagsisimula sa ibabang bahagi ng dahon, pagkabansot o paninilaw ng buong puno, at ang paglitaw ng kulay puting pulbos (downy growth) sa magkabilang panig ng dahon.', 'Kung maliit lamang ang lupang tinataniman, agad na bunutin at alisin ang halamang may sakit; ibaon ito sa lalim na 20 sentimetro o higit pa, o sunugin ito nang malayo sa taniman upang hindi tangayin ng hangin ang mga puting pulbos.\r\nGamutin ang mga binhi bago itanim gamit ang mga metalaxyl fungicides tulad ng Apron o Ridomil (1 gramo ng gamot kada 1 kilo ng binhi).\r\nPatuyuin at ibilad nang maigi ang mga buto ng mais hanggang maabot ang moisture content na mas mababa sa 14% bago itabi o itanim upang bumaba ang tiyansa ng pag-atake ng sakit.\r\nMagsagawa ng crop rotation pagkatapos ng ani at huwag na huwag magtatanim ng tubo o sorghum dahil dito rin nabubuhay ang amag ng downy mildew.', 'Gumamit ng sertipikado at may tibay na binhi, gamutin ang binhi bago itanim, at patuyuin ang buto hanggang mas mababa sa 14% moisture content.', NULL, 0, '2026-07-19 07:16:22'),
(7, 'Corn Mosaic', NULL, 'Viral', 'medium', 'Ito ay ang paninilaw at pagpandak ng mais na dulot naman ng isang uri ng virus. Mapanganib ito dahil kaya nitong umatake sa kahit anong yugto o edad ng paglaki ng mais. Ang mga pangunahing sintomas nito ay ang pagkapusyaw o pagkupas ng kulay ng mga dahon, pagkakaroon ng maiigsing berdeng guhit sa buong dahon, at ang pagiging bansot ng puno ng mais.', 'Agad na bunutin at sirain ang mga halamang nagpapakita ng sintomas ng mosaic upang hindi na sipsipin ng mga apaya ang virus at ilipat sa iba.\r\nSugpuin ang mga insektong apaya (corn leaf aphid, bean aphid, at sugarcane aphids) na siyang vector o naglilipat ng virus sa pamamagitan ng angkop na pamamaraan.\r\nBunutin at linisin ang mga damo sa paligid gaya ng aguingay, seibung-sabongan, at pulang puwit na nagsisilbing alternatibong tirahan at pinamumugaran ng mga apaya.\r\nBawasan ang paglalagay ng labis na urea na nagpapalambot sa tanim at mag-aplay ng sapat at balanseng dami ng abonong complete (14-14-14).\r\nMagtanim ng mga matitibay at sertipikadong binhi na may laban sa sakit sa mga susunod na taniman, at iwasang magtanim ng tubo o sorghum pagkatapos.', 'Magtanim ng matitibay na binhi, iwasan ang sobrang urea, at linisin ang mga damong alternatibong tirahan ng mga apaya.', NULL, 0, '2026-07-19 07:16:22'),
(8, 'Bacterial Stalk Rot', NULL, 'Bacterial', 'high', 'Ito ay isang sakit kung saan nabubulok ang mga bahagi ng halaman dahil sa impeksyon ng bacteria. Madalas itong umatake sa mga bahagi ng bukid na madaling bahain o hindi maganda ang daluyan ng tubig (nabababad ang lupa). Ang mga sintomas nito ay ang pagkabulok ng puno at bunga, ang halaman ay nagiging malambot at may mabahong amoy (amoy nabubulok), natutuyo ang mga dahon mula sa ilalim pataas, at kalaunan ay natutumba ang puno ng mais dahil sa lambot ng ilalim nito.', 'Agad na bunutin at alisin sa taniman ang mga halamang may sakit; ibaon o sunugin ang mga ito nang malayo sa maisan upang hindi kumalat ang bacteria sa lupa at tubig.\r\nGumawa agad ng maayos na daluyan o kanal ng tubig upang maiwasan ang pagkaipon at pagkabasa ng mga puno na siyang paboritong kapaligiran ng bacteria.\r\nAlisin at linisin ang mga damong maaaring pagmulan at kapitan ng bacteria tulad ng kulitis o uray sa loob ng taniman.\r\nMaglagay ng sapat na abonong complete (14-14-14) base sa uri ng binhi (6-8 sako bawat ektarya para sa hybrid, at 4-6 sako para sa OPV) upang mapalakas ang halaman.\r\nHuwag magtatanim ng tubo at sorghum pagkatapos ng mais upang maputol ang siklo ng bacteria sa lupa.', 'Gumawa ng maayos na daluyan ng tubig, iwasan ang siksikang pagtatanim, at alisin ang mga damong kulitis o uray.', NULL, 0, '2026-07-19 07:16:22'),
(9, 'Nitrogen Deficiency', NULL, 'Deficiency', 'medium', 'Ito ay ang kakulangan sa nutrisyong Nitroheno na kailangan ng mais para sa malulusog, luntiang dahon at mabilis na paglaki. Ang kakulangang ito ay kapansin-pansin sa pamamagitan ng pangkalahatang pagbansot ng halaman, pagkakaroon ng manipis na katawan, at ang malinaw na paninilaw ng mga dahon na palaging nagsisimula sa pinakaibabang bahagi o sa mga matatandang dahon. Ang kakaibang katangian ng paninilaw na ito ay ang pagbuo ng hugis letrang \"V\" na nagsisimula sa mismong dulo ng dahon at gumagapang patungo sa gitnang litid nito habang ang mga gilid ay nananatiling berde sa unang yugto.', 'Maglagay agad ng dagliang-tatalab na abonong mayaman sa nitroheno gaya ng Urea (46-0-0) sa pamamagitan ng sidedressing (paglalagay sa gilid ng puno) habang maaga pa at aktibo pa sa paglaki ang mais upang maibalik ang luntiang kulay ng mga dahon.\r\nKung masyadong basa o baha ang lupa na siyang dahilan ng pagkaanod ng nutrisyon, gumawa agad ng drainage o daluyan upang maalis ang naiipong tubig, at saka mag-abono muli kapag tuyo na ang lupa.', 'Magsagawa ng soil testing bago magtanim upang malaman ang tamang dami at maglagay ng organikong pataba.', NULL, 0, '2026-07-19 07:16:22'),
(10, 'Phosphorus Deficiency', NULL, 'Deficiency', 'medium', 'Ito ay ang kakulangan sa nutrisyong Posporo na mahalaga para sa maagang pag-unlad ng ugat at pagbuo ng mga butil ng mais. Ang pinakatampok na palatandaan ng kakulangang ito ay ang pagkakaroon ng kakaibang kulay ube o lila (purpling) sa mga dulo at gilid ng mga dahon, na madalas makita sa mga batang halaman kapag malamig ang panahon. Bukod dito, nagdudulot din ito ng kapansin-pansing mabagal na pag-unlad ng ugat, pagkaantala sa paglaki ng buong puno, at nagreresulta sa pagkabali o pagkakagulu-gulo (hindi diretso) ng hilera ng mga butil sa puso ng mais kapag dumating ang gapasan.', 'Maghabol ng pataba gamit ang mga natutunaw na high-phosphorus foliar fertilizer (pini-spray sa dahon) upang mabilis na masipsip ng halaman habang malamig o tuyo ang lupa.\r\nKung tuyo ang lupa kaya hindi masipsip ng ugat ang posporo, magsagawa agad ng controlled na pagdidilig o patubig upang matunaw ang posporo na nasa lupa at makuha ito ng mga ugat.', 'Gumamit ng sapat na abonong complete (14-14-14) o solophos sa panahon ng pagtatanim (basal application).', NULL, 0, '2026-07-19 07:16:22'),
(11, 'Potassium Deficiency', NULL, 'Deficiency', 'medium', 'Ito ay ang kakulangan sa nutrisyong Potasyo na kailangan ng mais para sa paglaban sa sakit, lakas ng estruktura ng katawan, at maayos na sirkulasyon ng tubig. Ang kakulangang ito ay makikita sa pamamagitan ng paninilaw at unti-unting pagkatuyo na mukhang sunog sa mga gilid ng dahon (leaf margins), na palaging nagsisimula sa mga ibabang dahon patungo sa itaas habang ang gitnang bahagi ng dahon ay nananatiling berde. Nagiging sanhi rin ito ng madalas na pagtumba ng puno (lodging) dahil sa mahinang katawan, at nagreresulta sa maliliit na puso na may kulang-kulang o bansot na butil sa dulo nito.', 'Maglagay agad ng Muriate of Potash (0-0-60) sa pamamagitan ng side-dressing o top-dressing sa mga apektadong hanay ng mais upang patigasin ang katawan at maiwasan ang pagtumba.\r\nSiguraduhing maayos ang supply ng tubig sa lupa dahil ang potasyo ay nangangailangan ng sapat na moisture upang epektibong maihatid ng halaman mula sa ugat patungo sa mga dahon at puso nito.', 'Ipasuri ang taba ng lupa bago magtanim at siguraduhing balanse ang ilalagay na complete fertilizer.', NULL, 0, '2026-07-19 07:16:22'),
(12, 'Uod sa Puso o Corn Earworm', NULL, 'Pest', 'high', 'Ito ay isang pesteng uod na mapaminsalang umaatake sa dahon, bulaklak, at bunga ng mais hanggang sa paglaki ng butil nito. Madali itong makilala kapag nakakakita ng pagkadikit-dikit sa bahagi ng bulaklak bago ito mamagpag ng pollen, at pagkakaroon ng mga uod na nanginginain mismo sa loob ng puso o bunga ng mais.', 'Hilahin at alisin ang mga bulaklak na makikitaan ng pagkadikit-dikit bago pa mamagpag ang pollen upang mapigilan ang pagtuloy ng mga uod sa puso.\r\nMaglagay o magpakawala agad ng mga kaibigang kulisap o organismo tulad ng Trichogramma at earwig na makukuha sa Department of Agriculture Region IV-CALABARZON (Brgy. Marowoy, Lipa City, Batangas) o sa Quezon Agricultural Research and Experiment Station (Lagalag, Tiaong, Quezon) upang kainin ang mga itlog at uod.\r\nMagpatupad ng pagsasalit-tanim ng ibang halaman tulad ng gulay pagkatapos ng ani upang mapigilan ang muling pagdami ng peste sa susunong na tanim.', 'Magsalit-tanim ng ibang halaman tulad ng gulay upang mapigil ang pagdami ng peste sa susunod na tanim.', NULL, 0, '2026-07-19 07:16:22'),
(13, 'Bagumbong o Corn Borer', NULL, 'Pest', 'critical', 'Ito ay isang matinding peste na higit na mapaminsala sa mga binhing pangkain at maaaring magdulot ng 20% hanggang 80% na kabawasan sa kabuuang ani. Ang mga malinaw na sintomas at pinsala nito ay ang pagkakaroon ng mga butas sa dahon, pagkasira sa mismong katawan ng mais, pagkakadikit-dikit at pagkaputol ng mga bulaklak, at ang pagkasira ng mga butil sa loob ng puso', 'Magbomba agad ng insecticide na may sangkap na neem o may Bt (Bacillus thuringiensis) kapag nakita ang mga maliliit na uod sa panahon ng pagkukumpol ng dahon.\r\nHilahin at sunugin agad ang mga punpon ng bulaklak na may impeksyon dahil ito ay nagsisilbing pagkain at pinamumugaran ng peste.\r\nMagpakawala ng trichogramma (maliit na putakti) at earwig (sipit-sipitan) sa taniman upang sugpuin ang mga itlog at uod ng bagumbong.\r\nMagtira ng damong uray sa paligid ng maisan dahil pinagbabahayan ito ng mga insektong kumakain sa itlog ng bagumbong.\r\nMagsalit-tanim ng ibang halaman gaya ng gulay pagkaani upang mapigilan ang pagdami ng peste sa susunod na siklo.', 'Magtira ng uray (damo) sa maisan na pinagbabahayan ng mga kulisap na kumakain ng itlog nito, at magsalit-tanim ng gulay.', NULL, 0, '2026-07-19 07:16:22'),
(14, 'Ngusong Kabayo sa Mais o Corn Planthopper', NULL, 'Pest', 'medium', 'Ito ay isang insektong sumisipsip sa katas ng mais na kadalasang matatagpuan sa ilalim ng mga dahon. Ang pinsala nito ay makikita sa pamamagitan ng pagkatuyo ng dahon at ang pagkakaroon ng mga itim na amag (sooty mold) sa mga dahon at sa buong halaman dulot ng malagkit na katas na inilalabas ng insekto.', 'Kapag nakitaan ng mataas na bilang ng ngusong kabayo ang kasalukuyang tanim, ipagpaliban muna ang susunod na pagtatanim ng mais sa areang ito upang magutom at mamatay ang mga peste.\r\nItigil ang labis na paglalagay ng abonong nitroheno (urea) na nagpapalambot sa katawan ng tanim at umaakit sa peste, at lumipat sa sapat na paggamit ng abonong complete (14-14-14).\r\nMakipag-ugnayan agad sa pinakamalapit na tanggapan ng Department of Agriculture upang ipasuri ang taba ng lupa at makakuha ng eksaktong rekomendasyon sa pag-abono.\r\nMagsalit-tanim ng mga halamang legumbre sa mga susunod na taniman na nagpapabaon ng nutrisyon at pinamumugaran ng mga kaibigang putakti.\r\nIwasan ang dikit-dikit na pagtatanim ng mga puno ng mais sa mga susunod na siklo.', 'Iwasan ang dikit-dikit na pagtatanim, huwag sobrahan ang urea, at magsalit-tanim ng mga legumbre.', NULL, 0, '2026-07-19 07:16:22'),
(15, 'Fall Armyworm (FAW)', NULL, 'Pest', 'critical', 'Ito ay isang lubhang mapaminsalang pesteng uod na may natatanging hugis letrang \'Y\' sa kanyang ulo at apat na magkakapantay na tuldok sa dulo ng buntot. Karaniwang nanginginain ang mga ito sa buong araw at nagdudulot ng mga sintomas tulad ng cellophane-look o tila kinayod na bahagi ng dahon, malalaking butas sa dahon, bali o sirang talbos, at pagkasira ng bulaklak at bunga ng mais.', 'Mag-spray agad ng angkop at rehistradong kemikal na pamuksa; tiyakin ang tamang pagtitimpla at pagbomba, at iwasan ang sunod-sunod na paggamit ng parehong uri ng kemikal upang hindi magkaroon ng resistensiya ang FAW.\r\nGumamit ng mga pagkain na pang-bitag o food trap na gawa sa pinaghalong molasses o palyat at tubig upang mapadikit ang pakpak ng mga aliparo (moths) at mamatay nang hindi na makapangitlog.\r\nMaglagay ng pang-bitag sa lalaking aliparo (pheromone lure) upang masubaybayan ang tindi ng atake at malaman kung kailangan nang mag-spray ng pamatay-uod.\r\nMagpakawala ng mga kaibigang kulisap gaya ng trichogramma, earwig, o gamitin ang kaibigang amag na Metarhizium upang patayin ang peste.\r\nAruhing maigi ang lupa pagkatapos ng ani upang malantad sa araw at mamatay ang mga uod-tulog (pupae) na nasa ilalim ng lupa.', 'Araruhing maigi ang lupa upang malantad ang mga uod-tulog, at gumamit ng pheromone lure o food trap para sa pag-monitor.', NULL, 0, '2026-07-19 07:16:22');

-- --------------------------------------------------------

--
-- Table structure for table `disease_cases`
--

CREATE TABLE `disease_cases` (
  `case_id` int(11) NOT NULL,
  `reference_id` varchar(50) NOT NULL,
  `farm_id` int(11) NOT NULL,
  `farmer_id` int(11) NOT NULL,
  `barangay_id` int(11) DEFAULT NULL,
  `disease_id` int(11) DEFAULT NULL,
  `reported_by` int(11) NOT NULL,
  `report_date` datetime NOT NULL,
  `growth_stage` varchar(50) DEFAULT NULL,
  `date_planted` date DEFAULT NULL,
  `planting_date` date DEFAULT NULL,
  `plants_affected` int(11) DEFAULT NULL,
  `total_plants` int(11) DEFAULT NULL,
  `infection_percentage` decimal(5,2) DEFAULT NULL,
  `severity` enum('low','moderate','high','critical') DEFAULT NULL,
  `description` text DEFAULT NULL,
  `photo_evidence` varchar(255) DEFAULT NULL,
  `status` enum('pending','verified','rejected','resolved') DEFAULT 'pending',
  `verified_by` int(11) DEFAULT NULL,
  `verified_date` date DEFAULT NULL,
  `treatment_recommendation` text DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `remarks` text DEFAULT NULL,
  `recommendation_text` text DEFAULT NULL,
  `recommendation_sent` tinyint(1) NOT NULL DEFAULT 0,
  `recommendation_sent_at` timestamp NULL DEFAULT NULL,
  `farmer_reply_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `farmer_reply_at` timestamp NULL DEFAULT NULL,
  `source` enum('manual_report','scan') NOT NULL DEFAULT 'manual_report',
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `gps_accuracy` decimal(8,2) DEFAULT NULL,
  `altitude` decimal(10,2) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `rejected_by` int(11) DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `disease_cases`
--

INSERT INTO `disease_cases` (`case_id`, `reference_id`, `farm_id`, `farmer_id`, `barangay_id`, `disease_id`, `reported_by`, `report_date`, `growth_stage`, `date_planted`, `planting_date`, `plants_affected`, `total_plants`, `infection_percentage`, `severity`, `description`, `photo_evidence`, `status`, `verified_by`, `verified_date`, `treatment_recommendation`, `follow_up_date`, `created_at`, `updated_at`, `remarks`, `recommendation_text`, `recommendation_sent`, `recommendation_sent_at`, `farmer_reply_text`, `farmer_reply_at`, `source`, `latitude`, `longitude`, `gps_accuracy`, `altitude`, `verified_at`, `rejected_by`, `rejected_at`, `resolved_by`, `resolved_at`) VALUES
(66, 'REF-2026-D637', 0, 5, 19, 1, 0, '2026-04-20 00:16:15', 'VE: Emergence (Day 8)', '2026-04-12', NULL, NULL, NULL, NULL, 'critical', '', NULL, 'resolved', 12, '2026-09-21', NULL, NULL, '2026-04-19 16:16:15', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, '2026-09-21 09:22:53', NULL, NULL, 12, '2026-09-21 09:23:10'),
(67, 'REF-2026-F359', 0, 2, 22, 2, 0, '2026-04-20 00:18:05', 'V2: 2nd Leaf (Day 13)', '2026-04-07', NULL, NULL, NULL, NULL, 'high', '', '1776615485_evidence_1108.png', 'verified', NULL, NULL, NULL, NULL, '2026-04-19 16:18:05', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(68, 'REF-2026-B88E', 0, 1, 5, 3, 0, '2026-04-20 00:19:23', 'V4: 4th Leaf (Day 19)', '2026-04-01', NULL, NULL, NULL, NULL, 'low', '', NULL, 'verified', NULL, NULL, NULL, NULL, '2026-04-19 16:19:23', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(69, 'REF-2026-FBAA', 0, 3, 4, 2, 0, '2026-04-20 00:20:54', 'R3: Milk (Day 84)', '2026-01-26', NULL, NULL, NULL, NULL, 'critical', '', '1776615654_evidence_1182.png', 'verified', NULL, NULL, NULL, NULL, '2026-04-19 16:20:54', '2026-10-06 10:15:35', '', 'Ito ang dapat mong gawin:\r\nMag-spray ng angkop na systemic fungicide kapag nakita ang mga sugat bago o sa mismong panahon ng pag-umbok ng bulaklak (tasseling) upang mapigilan ang pag-akyat ng amag sa mga itaas na dahon na kritikal sa pagpapalaki ng butil.', 1, '2026-08-07 10:05:18', NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(70, 'REF-2026-0380', 0, 4, 34, 4, 0, '2026-04-20 00:21:59', 'VE: Emergence (Day 6)', '2026-04-14', NULL, NULL, NULL, NULL, 'critical', '', '1776615719_evidence_2647.png', 'verified', NULL, NULL, NULL, NULL, '2026-04-19 16:21:59', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(71, 'REF-2026-860F', 0, 8, 19, 1, 0, '2026-04-20 00:50:04', 'V8: 8th Leaf (Day 35)', '2026-03-16', NULL, NULL, NULL, NULL, 'moderate', '', '1776617404_evidence_5554.png', 'resolved', NULL, NULL, NULL, NULL, '2026-04-19 16:50:04', '2026-10-06 10:15:35', 'fued ', 'Ito ang dapat mong gawin:\r\nMag-spray agad ng angkop at rehistradong fungicide (gaya ng azoxystrobin o tebuconazole) sa mga dahon kapag nakitaan ng mga unang paltos.', 1, '2026-08-07 09:24:42', NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(72, 'REF-2026-CEA5', 0, 9, 22, 999, 0, '2026-04-20 00:50:50', 'V1: 1st Leaf (Day 10)', '2026-04-10', NULL, NULL, NULL, NULL, 'low', '', '1776617450_evidence_6371.png', 'verified', NULL, NULL, NULL, NULL, '2026-04-19 16:50:50', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(73, 'REF-2026-7F5C', 0, 6, 51, 4, 0, '2026-04-20 00:54:22', 'VE: Emergence (Day 1)', '2026-04-20', NULL, NULL, NULL, NULL, 'low', '', '1776617662_evidence_7277.png', 'verified', NULL, NULL, NULL, NULL, '2026-04-19 16:54:22', '2026-10-06 10:15:35', '', 'asd', 1, '2026-08-07 09:04:35', NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(74, 'REF-2026-7CA7', 0, 1, 44, 2, 0, '2026-04-20 00:55:24', 'R3: Milk (Day 82)', '2026-01-28', NULL, NULL, NULL, NULL, 'moderate', '', NULL, 'rejected', NULL, NULL, NULL, NULL, '2026-04-19 16:55:24', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(75, 'REF-2026-4202', 0, 2, 14, 3, 0, '2026-04-20 00:55:47', 'VT: Tasseling (Day 60)', '2026-02-19', NULL, NULL, NULL, NULL, 'low', '', NULL, 'resolved', NULL, NULL, NULL, NULL, '2026-04-19 16:55:47', '2026-10-06 22:58:29', 'JDFJsdjfsdhfsg haejog ', 'Ito ang dapat mong gawin:\r\nMag-aplay agad ng fungicide (gaya ng strobilurin o triazole) kapag ang mga parihabang sugat ay nagsisimulang lumitaw sa mga dahon malapit sa puso ng mais baho mag-pollen.', 1, '2026-10-06 22:58:29', NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(76, 'REF-2026-0DCB', 0, 1, 3, 4, 0, '2026-07-17 13:50:39', 'V3: 3rd Leaf (Day 16)', '2026-07-01', NULL, NULL, NULL, NULL, 'low', '', NULL, 'resolved', NULL, NULL, NULL, NULL, '2026-07-17 05:50:39', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 12, '2026-09-21 09:07:06'),
(77, 'REF-2026-E4CE', 0, 3, 15, 999, 0, '2026-07-19 12:53:09', 'V1: 1st Leaf (Day 10)', '2026-07-09', NULL, NULL, NULL, NULL, 'low', '', NULL, 'verified', NULL, NULL, NULL, NULL, '2026-07-19 04:53:09', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(78, 'REF-2026-D2CB', 0, 6, 2, 8, 0, '2026-07-19 16:12:40', 'R6: Physiological Maturity', '2026-03-12', NULL, NULL, NULL, NULL, 'moderate', '', '1784448760_evidence_6437_0.png,1784448760_evidence_9894_1.png,1784448760_evidence_8961_2.png,1784448760_evidence_5560_3.png', 'resolved', NULL, NULL, NULL, NULL, '2026-07-19 08:12:40', '2026-10-06 10:15:35', '', 'Agad na bunutin at alisin sa taniman ang mga halamang may sakit; ibaon o sunugin ang mga ito nang malayo sa maisan upang hindi kumalat ang bacteria sa lupa at tubig.\r\nGumawa agad ng maayos na daluyan o kanal ng tubig upang maiwasan ang pagkaipon at pagkabasa ng mga puno na siyang paboritong kapaligiran ng bacteria.', 1, '2026-08-06 13:08:37', NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(80, 'REF-2026-2628', 0, 1, 4, 8, 0, '2026-07-19 16:45:25', 'V1: 1st Leaf (Day 11)', '2026-07-08', NULL, NULL, NULL, NULL, 'moderate', 'asdas', '1784450725_evidence_4926_0.png,1784450725_evidence_8045_1.png', 'resolved', NULL, NULL, NULL, NULL, '2026-07-19 08:45:25', '2026-10-06 10:15:35', 'DFSDFSDFsdfsdfsdff', 'Agad na bunutin at alisin sa taniman ang mga halamang may sakit; ibaon o sunugin ang mga ito nang malayo sa maisan upang hindi kumalat ang bacteria sa lupa at tubig.  Gumawa agad ng maayos na daluyan o kanal ng tubig upang maiwasan ang pagkaipon at pagkabasa ng mga puno na siyang paboritong kapaligiran ng bacteria.  \r\n\r\nAlisin at linisin ang mga damong maaaring pagmulan at kapitan ng bacteria tulad ng kulitis o uray sa loob ng taniman.  \r\n\r\nMaglagay ng sapat na abonong complete (14-14-14) base sa uri ng binhi (6-8 sako bawat ektarya para sa hybrid, at 4-6 sako para sa OPV) upang mapalakas ang halaman.  \r\n\r\nHuwag magtatanim ng tubo at sorghum pagkatapos ng mais upang maputol ang siklo ng bacteria sa lupa.', 1, '2026-07-19 08:45:41', NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(81, 'REF-2026-2628', 0, 1, 4, 11, 0, '2026-07-19 16:45:25', 'V1: 1st Leaf (Day 11)', '2026-07-08', NULL, NULL, NULL, NULL, 'moderate', 'asdas', '1784450725_evidence_4926_0.png,1784450725_evidence_8045_1.png', 'verified', NULL, NULL, NULL, NULL, '2026-07-19 08:45:25', '2026-10-06 10:15:35', '', 'FIELD INSPECTION SCHEDULED\r\nA field inspection has been scheduled for your farm on Thursday, September 3, 2026 at 2:30 PM. Please be available on-site at that time.', 1, '2026-08-30 11:35:25', NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(82, 'REF-2026-4464', 0, 10, 2, 8, 0, '2026-07-27 14:06:56', 'V4: 4th Leaf (Day 19)', '2026-07-08', NULL, NULL, NULL, NULL, 'critical', 'too much pest\\r\\n', '1785132416_evidence_2981_0.jpg', 'verified', NULL, NULL, NULL, NULL, '2026-07-27 06:06:56', '2026-10-06 10:15:35', '', 'FIELD INSPECTION SCHEDULED\r\nA field inspection has been scheduled for your farm on Thursday, September 3, 2026 at 2:00 PM. Please be available on-site at that time.', 1, '2026-08-30 11:44:11', NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(83, 'REF-2026-9AD6', 0, 10, 3, 999, 0, '2026-07-27 20:24:59', 'V5: 5th Leaf (Day 25)', '2026-07-02', NULL, NULL, NULL, NULL, 'low', '', '1785155099_evidence_1651_0.jpg', 'resolved', NULL, NULL, NULL, NULL, '2026-07-27 12:24:59', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(85, 'REF-2026-866E', 0, 2, 2, 8, 0, '2026-08-07 17:45:22', 'V1: 1st Leaf (Day 9)', '2026-07-29', NULL, NULL, NULL, NULL, 'moderate', 'sdasd', '1786095922_evidence_3919_0.jpg', 'verified', NULL, NULL, NULL, NULL, '2026-08-07 09:45:22', '2026-10-06 10:15:35', '', 'FIELD INSPECTION SCHEDULED\r\nA field inspection has been scheduled for your farm on Thursday, September 3, 2026 at 2:00 PM. Please be available on-site at that time.', 1, '2026-08-30 11:43:33', NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(86, 'REF-2026-11A1', 0, 2, 9, 7, 0, '2026-09-04 15:00:54', 'VE: Emergence (Day 3)', '2026-09-01', NULL, NULL, NULL, NULL, 'moderate', '', NULL, 'pending', NULL, NULL, NULL, NULL, '2026-09-04 07:00:54', '2026-10-06 10:15:35', NULL, NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(87, 'REF-2026-342F', 0, 2, 2, 5, 0, '2026-09-04 15:03:17', 'VE: Emergence (Day 3)', '2026-09-01', NULL, NULL, NULL, NULL, 'moderate', '', NULL, 'verified', 12, '2026-10-06', NULL, NULL, '2026-09-04 07:03:17', '2026-10-06 22:53:52', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, '2026-10-06 22:53:52', NULL, NULL, NULL, NULL),
(88, 'REF-2026-A3C3', 0, 6, 4, 8, 0, '2026-09-04 15:14:47', 'VE: Emergence (Day 2)', '2026-09-02', NULL, NULL, NULL, NULL, 'low', '', NULL, 'rejected', NULL, NULL, NULL, NULL, '2026-09-04 07:14:47', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(89, 'REF-2026-01A5', 0, 6, 5, 999, 0, '2026-09-04 15:27:06', 'VE: Emergence (Day 1)', '2026-09-03', NULL, NULL, NULL, NULL, 'critical', '', '1788506826_evidence_4439_0.png', 'verified', NULL, NULL, NULL, NULL, '2026-09-04 07:27:06', '2026-10-06 10:15:35', '', 'FIELD INSPECTION SCHEDULED\r\nA field inspection has been scheduled for your farm on Saturday, September 5, 2026 at 7:30 AM. Please be available on-site at that time.', 1, '2026-09-04 07:30:02', NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(90, 'REF-2026-73B2', 0, 5, 3, 10, 0, '2026-09-04 15:33:23', 'VE: Emergence (Day 2)', '2026-09-02', NULL, NULL, NULL, NULL, 'high', '', NULL, 'verified', NULL, NULL, NULL, NULL, '2026-09-04 07:33:23', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(91, 'REF-2026-73B2', 0, 5, 3, 12, 0, '2026-09-04 15:33:23', 'VE: Emergence (Day 2)', '2026-09-02', NULL, NULL, NULL, NULL, 'high', '', NULL, 'verified', NULL, NULL, NULL, NULL, '2026-09-04 07:33:23', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(92, 'REF-2026-B8D7', 0, 5, 2, 8, 0, '2026-09-04 16:25:04', 'VE: Emergence (Day 2)', '2026-09-02', NULL, NULL, NULL, NULL, 'high', '', NULL, 'verified', NULL, NULL, NULL, NULL, '2026-09-04 08:25:04', '2026-10-06 10:15:35', '', 'FIELD INSPECTION CANCELLED\r\nThe field inspection scheduled for your farm on Monday, September 14, 2026 has been cancelled due to unforeseen circumstances. Please wait for further scheduling.', 1, '2026-09-12 18:59:23', NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(93, 'REF-2026-B8D7', 0, 5, 2, 13, 0, '2026-09-04 16:25:04', 'VE: Emergence (Day 2)', '2026-09-02', NULL, NULL, NULL, NULL, 'high', '', NULL, 'verified', NULL, NULL, NULL, NULL, '2026-09-04 08:25:04', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(94, 'REF-2026-B8D7', 0, 5, 2, 1, 0, '2026-09-04 16:25:04', 'VE: Emergence (Day 2)', '2026-09-02', NULL, NULL, NULL, NULL, 'high', '', NULL, 'verified', NULL, NULL, NULL, NULL, '2026-09-04 08:25:04', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(95, 'REF-2026-5C68', 0, 6, 3, 999, 0, '2026-09-10 11:23:43', 'VE: Emergence (Day 8)', '2026-09-02', NULL, NULL, NULL, NULL, 'moderate', '', '1789010623_evidence_4097_0.png', 'verified', NULL, NULL, NULL, NULL, '2026-09-10 03:23:43', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(96, 'REF-2026-A380', 0, 5, 1, 5, 0, '2026-09-13 02:11:52', 'VE: Emergence (Day 4)', '2026-09-09', NULL, NULL, NULL, NULL, 'moderate', '', NULL, 'verified', 12, '2026-10-06', NULL, NULL, '2026-09-12 18:11:52', '2026-10-06 22:57:21', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, '2026-10-06 22:57:21', NULL, NULL, NULL, NULL),
(97, 'REF-2026-902C', 0, 2, 2, 1, 0, '2026-09-13 02:14:34', 'VE: Emergence (Day 4)', '2026-09-09', NULL, NULL, NULL, NULL, 'moderate', '', NULL, 'verified', NULL, NULL, NULL, NULL, '2026-09-12 18:14:34', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(98, 'REF-2026-991F', 0, 2, 1, 6, 0, '2026-09-13 02:14:59', 'V2: 2nd Leaf (Day 13)', '2026-08-31', NULL, NULL, NULL, NULL, 'moderate', '', NULL, 'verified', NULL, NULL, NULL, NULL, '2026-09-12 18:14:59', '2026-10-06 10:15:35', '', 'Ito ang dapat mong gawin:\r\nKung maliit lamang ang lupang tinataniman, agad na bunutin at alisin ang halamang may sakit; ibaon ito sa lalim na 20 sentimetro o higit pa, o sunugin ito nang malayo sa taniman upang hindi tangayin ng hangin ang mga puting pulbos.', 1, '2026-09-12 18:15:29', NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(99, 'REF-2026-60B2', 0, 6, 4, 5, 0, '2026-09-13 02:18:32', 'V2: 2nd Leaf (Day 13)', '2026-08-31', NULL, NULL, NULL, NULL, 'moderate', '', NULL, 'verified', NULL, NULL, NULL, '2026-09-23', '2026-09-12 18:18:32', '2026-10-06 10:15:35', '', 'FIELD INSPECTION SCHEDULED\r\nA field inspection has been scheduled for your farm on Wednesday, September 23, 2026 at 7:30 AM. Please be available on-site at that time.', 1, '2026-09-12 18:51:45', NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(100, 'REF-2026-63D8', 0, 5, 4, 5, 0, '2026-09-13 02:58:25', 'V2: 2nd Leaf (Day 12)', '2026-09-01', NULL, NULL, NULL, NULL, 'low', '', NULL, 'verified', 12, '2026-09-21', NULL, NULL, '2026-09-12 18:58:25', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, '2026-09-21 13:26:21', NULL, NULL, NULL, NULL),
(101, 'REF-2026-63D8', 0, 5, 4, 1, 0, '2026-09-13 02:58:25', 'V2: 2nd Leaf (Day 12)', '2026-09-01', NULL, NULL, NULL, NULL, 'low', '', NULL, 'verified', 12, '2026-09-21', NULL, NULL, '2026-09-12 18:58:25', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, '2026-09-21 13:26:21', NULL, NULL, NULL, NULL),
(102, 'REF-2026-63D8', 0, 5, 4, 15, 0, '2026-09-13 02:58:25', 'V2: 2nd Leaf (Day 12)', '2026-09-01', NULL, NULL, NULL, NULL, 'low', '', NULL, 'verified', 12, '2026-09-21', NULL, NULL, '2026-09-12 18:58:25', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, '2026-09-21 13:26:21', NULL, NULL, NULL, NULL),
(103, 'REF-2026-E3BC', 0, 6, 2, 13, 0, '2026-09-13 03:00:47', 'VE: Emergence (Day 4)', '2026-09-09', NULL, NULL, NULL, NULL, 'high', '', NULL, 'verified', NULL, NULL, NULL, NULL, '2026-09-12 19:00:47', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(104, 'REF-2026-E3BC', 0, 6, 2, 5, 0, '2026-09-13 03:00:47', 'VE: Emergence (Day 4)', '2026-09-09', NULL, NULL, NULL, NULL, 'high', '', NULL, 'verified', NULL, NULL, NULL, NULL, '2026-09-12 19:00:47', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(105, 'REF-2026-E3BC', 0, 6, 2, 1, 0, '2026-09-13 03:00:47', 'VE: Emergence (Day 4)', '2026-09-09', NULL, NULL, NULL, NULL, 'high', '', NULL, 'verified', NULL, NULL, NULL, NULL, '2026-09-12 19:00:47', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(106, 'REF-2026-73E3', 0, 2, 4, 1, 0, '2026-09-13 03:05:24', 'V2: 2nd Leaf (Day 12)', '2026-09-01', NULL, NULL, NULL, NULL, 'moderate', '', NULL, 'verified', NULL, NULL, NULL, NULL, '2026-09-12 19:05:24', '2026-10-06 10:15:35', '', 'FIELD INSPECTION CANCELLED\r\nThe field inspection scheduled for your farm on Tuesday, September 15, 2026 has been cancelled due to unforeseen circumstances. Please wait for further scheduling.', 1, '2026-09-13 08:36:52', NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(107, 'REF-2026-73E3', 0, 2, 4, 5, 0, '2026-09-13 03:05:24', 'V2: 2nd Leaf (Day 12)', '2026-09-01', NULL, NULL, NULL, NULL, 'moderate', '', NULL, 'verified', NULL, NULL, NULL, NULL, '2026-09-12 19:05:24', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(108, 'REF-2026-73E3', 0, 2, 4, 13, 0, '2026-09-13 03:05:24', 'V2: 2nd Leaf (Day 12)', '2026-09-01', NULL, NULL, NULL, NULL, 'moderate', '', NULL, 'verified', NULL, NULL, NULL, NULL, '2026-09-12 19:05:24', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(109, 'REF-2026-8246', 0, 5, 2, 13, 0, '2026-09-13 19:30:16', 'V2: 2nd Leaf (Day 12)', '2026-09-01', NULL, NULL, NULL, NULL, 'low', '', NULL, 'verified', NULL, NULL, NULL, NULL, '2026-09-13 11:30:16', '2026-10-06 10:15:35', '', 'Ito ang mga dapat mong gawin:\r\n1. Magbomba agad ng insecticide na may sangkap na neem o may Bt (Bacillus thuringiensis) kapag nakita ang mga maliliit na uod sa panahon ng pagkukumpol ng dahon.\r\n2. Hilahin at sunugin agad ang mga punpon ng bulaklak na may impeksyon dahil ito ay nagsisilbing pagkain at pinamumugaran ng peste.', 1, '2026-09-13 11:30:39', NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(110, 'REF-2026-1552', 0, 6, 3, 3, 0, '2026-09-13 19:36:51', 'VE: Emergence (Day 5)', '2026-09-08', NULL, NULL, NULL, NULL, 'moderate', '', NULL, 'verified', NULL, NULL, NULL, NULL, '2026-09-13 11:36:51', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(111, 'REF-2026-FFAC', 0, 11, 2, 8, 0, '2026-09-13 20:03:10', 'V2: 2nd Leaf (Day 12)', '2026-09-01', NULL, NULL, NULL, NULL, 'low', '', NULL, 'verified', NULL, NULL, NULL, NULL, '2026-09-13 12:03:10', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(112, 'REF-2026-FFAC', 0, 11, 2, 1, 0, '2026-09-13 20:03:10', 'V2: 2nd Leaf (Day 12)', '2026-09-01', NULL, NULL, NULL, NULL, 'low', '', NULL, 'verified', NULL, NULL, NULL, NULL, '2026-09-13 12:03:10', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(113, 'REF-2026-FFAC', 0, 11, 2, 15, 0, '2026-09-13 20:03:10', 'V2: 2nd Leaf (Day 12)', '2026-09-01', NULL, NULL, NULL, NULL, 'low', '', NULL, 'verified', NULL, NULL, NULL, NULL, '2026-09-13 12:03:10', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(114, 'REF-2026-075A', 0, 10, 3, 3, 0, '2026-09-13 22:00:59', 'VE: Emergence (Day 3)', '2026-09-10', NULL, NULL, NULL, NULL, 'low', '', '1789308059_evidence_6906_0.jpg', 'rejected', NULL, NULL, NULL, NULL, '2026-09-13 14:00:59', '2026-10-06 10:15:35', 'IWAN', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, 12, '2026-09-21 09:19:41', NULL, NULL),
(115, 'REF-2026-8A46', 0, 10, 3, 8, 0, '2026-09-13 22:01:18', 'VE: Emergence (Day 4)', '2026-09-09', NULL, NULL, NULL, NULL, 'low', '', NULL, 'resolved', 12, '2026-09-21', NULL, NULL, '2026-09-13 14:01:18', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, '2026-09-21 08:54:05', NULL, NULL, 12, '2026-09-21 09:17:27'),
(116, 'REF-2026-3375', 0, 6, 5, 6, 0, '2026-09-13 22:02:02', 'V2: 2nd Leaf (Day 12)', '2026-09-01', NULL, NULL, NULL, NULL, 'moderate', '', NULL, 'verified', NULL, NULL, NULL, NULL, '2026-09-13 14:02:02', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(117, 'REF-2026-F99E', 0, 11, 3, 1, 0, '2026-09-13 22:32:43', 'V1: 1st Leaf (Day 11)', '2026-09-02', NULL, NULL, NULL, NULL, 'low', '', '1789309963_evidence_4022_0.jpg', 'verified', NULL, NULL, NULL, NULL, '2026-09-13 14:32:43', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(118, 'REF-2026-4A7D', 0, 8, 1, 8, 12, '2026-09-20 09:59:20', 'VE: Emergence (Day 6)', '2026-09-14', NULL, NULL, NULL, NULL, 'moderate', '', '1789869560_evidence_1518_0.jpg', 'resolved', 12, '2026-09-20', NULL, NULL, '2026-09-20 01:59:20', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, '2026-09-20 09:59:42', NULL, NULL, NULL, NULL),
(119, 'REF-2026-4A7D', 0, 8, 1, 5, 12, '2026-09-20 09:59:20', 'VE: Emergence (Day 6)', '2026-09-14', NULL, NULL, NULL, NULL, 'moderate', '', '1789869560_evidence_1518_0.jpg', 'resolved', 12, '2026-09-20', NULL, NULL, '2026-09-20 01:59:20', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, '2026-09-20 09:59:42', NULL, NULL, NULL, NULL),
(120, 'REF-2026-C8A1', 0, 7, 11, 15, 12, '2026-09-20 10:46:28', 'VE: Emergence (Day 3)', '2026-09-17', NULL, NULL, NULL, NULL, 'low', '', '1789872388_evidence_9618_0.jpg', 'verified', 12, '2026-09-20', NULL, NULL, '2026-09-20 02:46:28', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, '2026-09-20 14:06:00', NULL, NULL, NULL, NULL),
(121, 'REF-2026-C8A1', 0, 7, 11, 4, 12, '2026-09-20 10:46:28', 'VE: Emergence (Day 3)', '2026-09-17', NULL, NULL, NULL, NULL, 'low', '', '1789872388_evidence_9618_0.jpg', 'verified', 12, '2026-09-20', NULL, NULL, '2026-09-20 02:46:28', '2026-10-06 10:15:35', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, '2026-09-20 14:06:00', NULL, NULL, NULL, NULL),
(122, 'REF-2026-A72B', 0, 7, 3, 4, 12, '2026-09-20 11:11:44', 'V2: 2nd Leaf (Day 12)', '2026-09-08', NULL, NULL, NULL, NULL, 'low', '', '1789873904_evidence_2221_0.jpg', 'rejected', NULL, NULL, NULL, NULL, '2026-09-20 03:11:44', '2026-10-06 10:15:35', 'la namang sakit', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, 12, '2026-09-20 11:12:00', NULL, NULL),
(123, 'SCN-SAMPLE-01', 0, 2, 5, NULL, 2, '2026-09-20 10:42:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AI scan: Corn___Common_Rust detected. Confidence: 96.4%', 'uploads/scan_results/scan_5_1782904381.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-09-19 18:42:15', '2026-09-19 18:42:15', NULL, NULL, 0, NULL, NULL, NULL, 'scan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(124, 'SCN-SAMPLE-02', 0, 3, 14, NULL, 3, '2026-09-20 09:18:40', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AI scan: Healthy corn detected. Confidence: 91.7%', 'uploads/scan_results/scan_5_1787747279.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-09-19 17:18:40', '2026-09-19 17:18:40', NULL, NULL, 0, NULL, NULL, NULL, 'scan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(125, 'SCN-SAMPLE-03', 0, 1, 19, NULL, 1, '2026-09-19 16:05:22', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AI scan: Corn___Common_Rust detected. Confidence: 88.9%', 'uploads/scan_results/scan_5_1783008070.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-09-19 00:05:22', '2026-09-19 00:05:22', NULL, NULL, 0, NULL, NULL, NULL, 'scan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(126, 'SCN-SAMPLE-04', 0, 4, 2, NULL, 4, '2026-09-19 08:31:09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AI scan: Other detected. Confidence: 94.2%', 'uploads/scan_results/scan_5_1787751589.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-09-18 16:31:09', '2026-09-18 16:31:09', NULL, NULL, 0, NULL, NULL, NULL, 'scan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(127, 'SCN-SAMPLE-05', 0, 5, 37, NULL, 5, '2026-09-18 14:47:53', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AI scan: Healthy corn detected. Confidence: 97.3%', 'uploads/scan_results/scan_5_1787752931.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-09-17 22:47:53', '2026-09-17 22:47:53', NULL, NULL, 0, NULL, NULL, NULL, 'scan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(128, 'SCN-SAMPLE-06', 0, 2, 9, NULL, 2, '2026-09-18 11:12:30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AI scan: Corn__MLN detected. Confidence: 88.4%', 'uploads/scan_results/scan_5_1789288116.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-09-17 19:12:30', '2026-09-17 19:12:30', NULL, NULL, 0, NULL, NULL, NULL, 'scan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(129, 'SCN-SAMPLE-07', 0, 3, 13, NULL, 3, '2026-09-17 17:26:04', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AI scan: Corn___Common_Rust detected. Confidence: 92.8%', 'uploads/scan_results/scan_5_1783014590.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-09-17 01:26:04', '2026-09-17 01:26:04', NULL, NULL, 0, NULL, NULL, NULL, 'scan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(130, 'SCN-SAMPLE-08', 0, 1, 4, NULL, 1, '2026-09-16 13:09:47', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AI scan: Other detected. Confidence: 86.5%', 'uploads/scan_results/scan_5_1787751590.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-09-15 21:09:47', '2026-09-15 21:09:47', NULL, NULL, 0, NULL, NULL, NULL, 'scan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(131, 'SCN-SAMPLE-09', 0, 4, 15, NULL, 4, '2026-09-16 07:55:18', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AI scan: Healthy corn detected. Confidence: 83.1%', 'uploads/scan_results/scan_5_1787752958.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-09-15 15:55:18', '2026-09-15 15:55:18', NULL, NULL, 0, NULL, NULL, NULL, 'scan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(132, 'SCN-SAMPLE-10', 0, 5, 1, NULL, 5, '2026-09-14 15:38:26', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AI scan: Corn___Common_Rust detected. Confidence: 90.6%', 'uploads/scan_results/scan_5_1783015318.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-09-13 23:38:26', '2026-09-13 23:38:26', NULL, NULL, 0, NULL, NULL, NULL, 'scan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(133, 'SCN-SAMPLE-11', 0, 2, 19, NULL, 2, '2026-09-09 10:20:11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AI scan: Healthy corn detected. Confidence: 95%', 'uploads/scan_results/scan_5_1787836104.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-09-08 18:20:11', '2026-09-08 18:20:11', NULL, NULL, 0, NULL, NULL, NULL, 'scan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(134, 'SCN-SAMPLE-12', 0, 3, 5, NULL, 3, '2026-08-28 09:44:37', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AI scan: Other detected. Confidence: 89.7%', 'uploads/scan_results/scan_5_1787752931.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-08-27 17:44:37', '2026-08-27 17:44:37', NULL, NULL, 0, NULL, NULL, NULL, 'scan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(135, 'SCN-SAMPLE-13', 0, 1, 14, NULL, 1, '2026-08-15 16:12:59', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AI scan: Corn___Common_Rust detected. Confidence: 84.3%', 'uploads/scan_results/scan_5_1783015360.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-08-15 00:12:59', '2026-08-15 00:12:59', NULL, NULL, 0, NULL, NULL, NULL, 'scan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(136, 'SCN-SAMPLE-14', 0, 4, 2, NULL, 4, '2026-07-30 12:03:08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AI scan: Healthy corn detected. Confidence: 99%', 'uploads/scan_results/scan_5_1789278647.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-07-29 20:03:08', '2026-07-29 20:03:08', NULL, NULL, 0, NULL, NULL, NULL, 'scan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(137, 'REF-1791087607112', 0, 12, NULL, NULL, 12, '2026-10-04 12:21:15', '6th Leaf (V6)', NULL, '2026-09-10', 6, NULL, NULL, NULL, 'madaming pagkain', 'uploads/case_1791087675_6ac1d43b6562f.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-10-04 04:21:15', '2026-10-04 04:21:15', NULL, NULL, 0, NULL, NULL, NULL, 'manual_report', 14.18938150, 121.16700820, 13.33, 57.10, NULL, NULL, NULL, NULL, NULL),
(138, 'REF-1791093976288', 0, 12, NULL, NULL, 12, '2026-10-04 14:07:03', 'Physiological Maturity (R6)', NULL, '2026-06-10', 4, NULL, NULL, NULL, 'maputi', 'uploads/case_1791094023_6ac1ed07abab5.jpg', 'resolved', 12, '2026-10-04', NULL, NULL, '2026-10-04 06:07:03', '2026-10-04 06:10:38', '', NULL, 0, NULL, NULL, NULL, 'manual_report', 14.18941520, 121.16700410, 20.00, 57.00, '2026-10-04 06:09:31', NULL, NULL, 12, '2026-10-04 06:10:38'),
(139, 'REF-1791094306679', 0, 12, NULL, NULL, 12, '2026-10-04 14:11:57', 'Dent (R5)', NULL, '2026-07-10', 3, NULL, NULL, NULL, 'hi', 'uploads/case_1791094318_6ac1ee2e11d7f.jpg', 'verified', 12, '2026-10-04', NULL, NULL, '2026-10-04 06:11:58', '2026-10-06 07:24:51', '', 'test 1', 1, '2026-10-06 07:18:04', '[{\"message\":\"hi, are u single\",\"created_at\":\"2026-10-04 06:13:34\"},{\"message\":\"yow\",\"created_at\":\"2026-10-04 06:17:03\"},{\"message\":\"ayaw kong maniwala sayo\",\"created_at\":\"2026-10-06 07:19:41\"},{\"message\":\"panay caloocan ka\",\"created_at\":\"2026-10-06 07:24:00\"},{\"message\":\"ok calamba\",\"created_at\":\"2026-10-06 07:24:51\"}]', '2026-10-06 07:24:51', 'manual_report', 14.18941750, 121.16700300, 20.00, 57.00, '2026-10-04 06:12:33', NULL, NULL, NULL, NULL),
(140, 'REF-1791174057748', 0, 12, NULL, NULL, 12, '2026-10-05 12:21:29', '7th Leaf (V7)', NULL, '2026-09-08', 2, NULL, NULL, NULL, 'red', 'uploads/case_1791174089_6ac325c9a4da1.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-10-05 04:21:29', '2026-10-05 04:21:29', NULL, NULL, 0, NULL, NULL, NULL, 'manual_report', 14.18940030, 121.16701000, 13.33, 57.00, NULL, NULL, NULL, NULL, NULL),
(141, 'REF-1791174118649', 0, 12, NULL, NULL, 12, '2026-10-05 12:22:11', '8th Leaf (V8)', NULL, '2026-09-03', 3, NULL, NULL, NULL, 'blue', 'uploads/case_1791174131_6ac325f38f9dc.jpg', 'verified', 12, '2026-10-06', NULL, NULL, '2026-10-05 04:22:11', '2026-10-07 05:43:18', '', 'dafuq', 1, '2026-10-07 05:43:18', '[{\"message\":\"haluh\",\"created_at\":\"2026-10-07 05:42:46\"}]', '2026-10-07 05:42:46', 'manual_report', 14.18939750, 121.16700940, 12.42, 57.00, '2026-10-06 06:01:45', NULL, NULL, NULL, NULL),
(142, 'SCN-REF-15', 0, 12, 17, NULL, 12, '2026-10-05 04:41:56', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AI scan: Other detected. Confidence: 69%', 'uploads/scan_results/scan_12_1791175316.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-10-05 04:41:56', '2026-10-05 04:41:56', NULL, NULL, 0, NULL, NULL, NULL, 'scan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(143, 'SCN-REF-16', 0, 12, 17, NULL, 12, '2026-10-05 04:41:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AI scan: Other detected. Confidence: 53.5%', 'uploads/scan_results/scan_12_1791175317.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-10-05 04:41:57', '2026-10-05 04:41:57', NULL, NULL, 0, NULL, NULL, NULL, 'scan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(144, 'SCN-REF-17', 0, 12, 17, NULL, 12, '2026-10-05 04:41:58', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AI scan: Other detected. Confidence: 60.9%', 'uploads/scan_results/scan_12_1791175318.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-10-05 04:41:58', '2026-10-05 04:41:58', NULL, NULL, 0, NULL, NULL, NULL, 'scan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(145, 'SCN-REF-18', 0, 12, 17, NULL, 12, '2026-10-06 05:56:54', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AI scan: Northern_Leaf_Blight detected. Confidence: 95.9%', 'uploads/scan_results/scan_12_1791266214.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-10-06 05:56:54', '2026-10-06 05:56:54', NULL, NULL, 0, NULL, NULL, NULL, 'scan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(146, 'SCN-REF-19', 0, 12, 17, NULL, 12, '2026-10-06 05:57:20', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AI scan: Northern_Leaf_Blight detected. Confidence: 99.2%', 'uploads/scan_results/scan_12_1791266240.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-10-06 05:57:20', '2026-10-06 05:57:20', NULL, NULL, 0, NULL, NULL, NULL, 'scan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(147, 'REF-2026-9A31', 0, 6, 9, 9, 12, '2026-10-06 06:38:51', 'V2: 2nd Leaf (Day 13)', '2026-09-23', NULL, NULL, NULL, NULL, 'low', '', '1791268731_evidence_2172_0.jpg', 'resolved', 12, '2026-10-06', NULL, NULL, '2026-10-06 06:38:51', '2026-10-06 22:45:05', '', NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, '2026-10-06 22:44:54', NULL, NULL, 12, '2026-10-06 22:45:05'),
(148, 'SCN-REF-20', 0, 12, 17, NULL, 12, '2026-10-06 09:06:45', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AI scan: Northern_Leaf_Blight detected. Confidence: 100%', 'uploads/scan_results/scan_12_1791277605.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-10-06 09:06:45', '2026-10-06 09:06:45', NULL, NULL, 0, NULL, NULL, NULL, 'scan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(149, 'SCN-REF-21', 0, 12, 17, NULL, 12, '2026-10-06 09:07:03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AI scan: Northern_Leaf_Blight detected. Confidence: 99.1%', 'uploads/scan_results/scan_12_1791277623.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-10-06 09:07:03', '2026-10-06 09:07:03', NULL, NULL, 0, NULL, NULL, NULL, 'scan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(150, 'SCN-REF-22', 0, 12, 17, NULL, 12, '2026-10-06 09:13:03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AI scan: Northern_Leaf_Blight detected. Confidence: 99.2%', 'uploads/scan_results/scan_12_1791277983.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-10-06 09:13:03', '2026-10-06 09:13:03', NULL, NULL, 0, NULL, NULL, NULL, 'scan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(151, 'SCN-REF-23', 0, 12, 17, NULL, 12, '2026-10-06 09:13:21', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AI scan: Northern_Leaf_Blight detected. Confidence: 99.9%', 'uploads/scan_results/scan_12_1791278001.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-10-06 09:13:21', '2026-10-06 09:13:21', NULL, NULL, 0, NULL, NULL, NULL, 'scan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(152, 'REF-1791279135949', 0, 12, NULL, 11, 12, '2026-10-06 17:32:29', 'Dent (R5)', NULL, '2026-07-11', 3, NULL, NULL, NULL, 'hi', 'uploads/case_1791279149_6ac4c02d2f0de.jpg', 'verified', 12, '2026-10-06', NULL, '2026-10-13', '2026-10-06 09:32:29', '2026-10-07 05:41:39', '', 'sheesh', 1, '2026-10-07 05:40:50', '[{\"message\":\"pano po gagawin\",\"created_at\":\"2026-10-06 10:20:50\"},{\"message\":\"what\",\"created_at\":\"2026-10-06 10:21:01\"},{\"message\":\"dafuq bro\",\"created_at\":\"2026-10-06 10:28:58\"},{\"message\":\"what yhe hell bro bat nag dodoble\",\"created_at\":\"2026-10-06 10:30:07\"},{\"message\":\"super\",\"created_at\":\"2026-10-07 04:22:23\"},{\"message\":\"luh\",\"created_at\":\"2026-10-07 04:32:28\"},{\"message\":\"lol\",\"created_at\":\"2026-10-07 04:33:53\"},{\"message\":\"what\",\"created_at\":\"2026-10-07 04:34:57\"},{\"message\":\"iwan\",\"created_at\":\"2026-10-07 04:39:22\"},{\"message\":\"gagi hangcolet\",\"created_at\":\"2026-10-07 04:39:44\"},{\"message\":\"pede na gawing messenger\",\"created_at\":\"2026-10-07 04:39:54\"},{\"message\":\"whaaattt\",\"created_at\":\"2026-10-07 04:40:51\"},{\"message\":\"you got me all screwed up\",\"created_at\":\"2026-10-07 05:26:05\"},{\"message\":\"watt\",\"created_at\":\"2026-10-07 05:31:54\"},{\"message\":\"haluh\",\"created_at\":\"2026-10-07 05:41:39\"}]', '2026-10-07 05:41:39', 'manual_report', 14.18555640, 121.16424240, 87.97, 67.40, '2026-10-06 10:20:29', NULL, NULL, NULL, NULL),
(153, 'SCN-REF-24', 0, 12, 17, NULL, 12, '2026-10-06 09:40:02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AI scan: Northern_Leaf_Blight detected. Confidence: 100%', 'uploads/scan_results/scan_12_1791279602.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-10-06 09:40:02', '2026-10-06 09:40:02', NULL, NULL, 0, NULL, NULL, NULL, 'scan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(154, 'SCN-REF-25', 0, 12, 17, NULL, 12, '2026-10-06 09:40:08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AI scan: Northern_Leaf_Blight detected. Confidence: 94.6%', 'uploads/scan_results/scan_12_1791279608.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-10-06 09:40:08', '2026-10-06 09:40:08', NULL, NULL, 0, NULL, NULL, NULL, 'scan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(155, 'SCN-REF-26', 0, 12, 17, NULL, 12, '2026-10-06 09:40:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AI scan: Northern_Leaf_Blight detected. Confidence: 100%', 'uploads/scan_results/scan_12_1791279615.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-10-06 09:40:15', '2026-10-06 09:40:15', NULL, NULL, 0, NULL, NULL, NULL, 'scan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(156, 'SCN-REF-27', 0, 12, 17, NULL, 12, '2026-10-06 09:42:58', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AI scan: Other detected. Confidence: 64.1%', 'uploads/scan_results/scan_12_1791279778.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-10-06 09:42:58', '2026-10-06 09:42:58', NULL, NULL, 0, NULL, NULL, NULL, 'scan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(157, 'SCN-REF-28', 0, 12, 17, NULL, 12, '2026-10-07 04:43:49', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AI scan: Northern_Leaf_Blight detected. Confidence: 99.5%', 'uploads/scan_results/scan_12_1791348229.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-10-07 04:43:49', '2026-10-07 04:43:49', NULL, NULL, 0, NULL, NULL, NULL, 'scan', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(158, 'REF-2026-CCB4', 0, 3, 5, 999, 12, '2026-10-07 04:51:57', 'VE: Emergence (Day 7)', '2026-09-30', NULL, NULL, NULL, NULL, 'low', '', '1791348717_evidence_5690_0.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-10-07 04:51:57', '2026-10-07 04:51:57', NULL, NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(159, 'REF-2026-1F66', 0, 8, 4, 999, 12, '2026-10-07 05:08:35', 'VE: Emergence (Day 1)', '2026-10-06', NULL, NULL, NULL, NULL, 'moderate', '', '1791349715_evidence_3300_0.jpg', 'pending', NULL, NULL, NULL, NULL, '2026-10-07 05:08:35', '2026-10-07 05:08:35', NULL, NULL, 0, NULL, NULL, NULL, 'manual_report', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `farmers`
--

CREATE TABLE `farmers` (
  `farmer_id` int(11) NOT NULL,
  `farmer_name` varchar(100) NOT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `age` int(11) NOT NULL,
  `contact_number` varchar(15) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `barangay_id` int(11) NOT NULL,
  `years_farming` int(11) DEFAULT NULL,
  `profile_photo` longblob DEFAULT NULL,
  `status` varchar(20) DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `profile_farmers` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `farmers`
--

INSERT INTO `farmers` (`farmer_id`, `farmer_name`, `gender`, `age`, `contact_number`, `email`, `password`, `barangay_id`, `years_farming`, `profile_photo`, `status`, `created_at`, `updated_at`, `profile_farmers`) VALUES
(1, 'Juan Dela Cruz', 'Male', 23, '09123456455', 'juan_92', '$2y$10$rB/zD9ELo4k0bYkONT1CWOZ06Z.ZVxeDKjdP8ImIuK4HjGMNZMz6m', 3, 22, NULL, 'active', '2026-04-19 16:15:49', '2026-04-19 16:15:49', NULL),
(2, 'John Lawrence Dela Cuesta', 'Male', 23, '09123456720', 'john_17', '$2y$10$e8OfO9F3/GZ1G8O3L3vX4uO50YQ.Oq0JpU7V42uXmC1v8I9Y/K2GG', 19, 2, NULL, 'active', '2026-04-19 16:15:49', '2026-07-31 04:48:39', '1776615349_b45a1401e373.jpg'),
(3, 'Maria Santos', 'Female', 55, '09123456714', 'maria_20', '$2y$10$jmfZi5o/uDhps2W6Wcw7KuenpmLkt8TrN/jR6X.BGIrGjjPgMJ0Ua', 5, 1, NULL, 'active', '2026-04-19 16:15:49', '2026-04-19 16:15:49', NULL),
(4, 'Ricardo Reyes', 'Male', 45, '09123456712', 'ricardo_96', '$2y$10$ghJbt4DYEfnk1vHcmEZXpuWA8nMDTbugVQgt.j4h07.XEo3ysWl/q', 2, 7, NULL, 'active', '2026-04-19 16:15:49', '2026-04-19 16:15:49', NULL),
(5, 'Elena Ramos', 'Female', 20, '09123456718', 'elena_67', '$2y$10$6j/xmzThe3sxw3yWIv0NluhlNaZLkVXV2KvQ4K1BIRn/ivLuOdhKO', 17, 12, NULL, 'active', '2026-04-19 16:15:49', '2026-04-19 16:15:49', NULL),
(6, 'Daniel Padilla', 'Male', 44, '09123456711', 'daniel_23', '$2y$10$m4qBnXnKPLPwLkQjBR.eDus5cc3GOLfA1i8ub44THNQS454BrNw8i', 5, NULL, NULL, 'active', '2026-04-19 16:49:27', '2026-09-13 14:08:42', '1776617367_6a1b0ff897a8.jpg'),
(7, 'Kathryn Bernardo', 'Male', 66, '09123456710', 'kathryn_17', '$2y$10$pYyGHafC17cSzG6jQcJwkeaqZnEI3wowkz3KEOrx74tyf.lUTs8Fi', 50, 23, NULL, 'active', '2026-04-19 16:49:27', '2026-04-19 16:49:27', NULL),
(8, 'Vic Sotto', 'Female', 99, '09123456788', 'vic_78', '$2y$10$02QqJdLnmOsPjHQW0l5yJ.BQIcFF2u7GHx.Panr3M/3c/D4OA.ClO', 18, 70, NULL, 'active', '2026-04-19 16:49:27', '2026-04-19 16:49:27', '1776617367_010f6cc484c6.jpg'),
(9, 'Maine Mendoza', 'Female', 33, '09123456718', 'maine_42', '$2y$10$zo2/u3WSXo09/75a/DdGsesZY1vndScrA8kChm3.NEKTVvAGiGV/2', 14, 34, NULL, 'active', '2026-04-19 16:49:27', '2026-04-19 16:49:27', '1776617367_c88af3594ed0.jpg'),
(10, 'Alden Richards', 'Male', 34, '09123456787', 'alden_23', '$2y$10$0QT4xXr51UmGVIQhmEg4legKNHXyE.E96y5pWIAKQYHlem/Ikq4ry', 4, 2026, NULL, 'active', '2026-04-19 16:49:27', '2026-09-13 17:07:51', '1776617367_1c3d698aba1e.jpg'),
(11, 'asdasdwerewrwer', 'Male', 24, '09999999999', 'asdasdwerewrwer_30', '$2y$10$B1IaocUjENQhjXw.eOWrmuRks1J8Z4Z8Fz59l./vv.hGKknkugrOC', 2, NULL, NULL, 'inactive', '2026-09-13 11:27:01', '2026-09-13 15:29:08', NULL),
(12, 'Lyka Manzanilla', 'Female', 52, '09941878176', 'lyka_48', '$2y$10$CdIJjC4Kxf9UIVDAmtFN2OqpqTCvniG0e50RxDllWX3OXLRxjMCam', 17, 2026, NULL, 'active', '2026-10-04 03:59:21', '2026-10-05 04:59:14', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notification_state`
--

CREATE TABLE `notification_state` (
  `farmer_id` int(11) NOT NULL,
  `event_key` varchar(120) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `details` text NOT NULL,
  `is_read` tinyint(4) NOT NULL DEFAULT 0,
  `dismissed` tinyint(4) NOT NULL DEFAULT 0,
  `alerted` tinyint(4) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_state`
--

INSERT INTO `notification_state` (`farmer_id`, `event_key`, `details`, `is_read`, `dismissed`, `alerted`, `created_at`) VALUES
(12, 'disease:138:resolved', '{\"eventKey\":\"disease:138:resolved\",\"title\":\"Report Resolved\",\"description\":\"Your report REF-1791093976288 has been resolved.\",\"type\":\"success\",\"time\":\"2:10 PM\",\"createdAt\":\"2026-10-04T06:10:53.761734Z\",\"caseId\":138,\"referenceId\":\"REF-1791093976288\",\"status\":\"resolved\",\"isRead\":false,\"alerted\":false,\"dismissed\":false}', 1, 1, 1, '2026-10-04 06:10:53'),
(12, 'disease:138:verified', '{\"eventKey\":\"disease:138:verified\",\"title\":\"Report Verified\",\"description\":\"Your report REF-1791093976288 has been verified.\",\"type\":\"success\",\"time\":\"2:09 PM\",\"createdAt\":\"2026-10-04T06:09:38.842141Z\",\"caseId\":138,\"referenceId\":\"REF-1791093976288\",\"status\":\"verified\",\"isRead\":false,\"alerted\":false,\"dismissed\":false}', 1, 1, 1, '2026-10-04 06:09:38'),
(12, 'disease:139:verified', '{\"eventKey\":\"disease:139:verified\",\"title\":\"Report Verified\",\"description\":\"Your report REF-1791094306679 has been verified.\",\"type\":\"success\",\"time\":\"2:12 PM\",\"createdAt\":\"2026-10-04T06:12:38.992759Z\",\"caseId\":139,\"referenceId\":\"REF-1791094306679\",\"status\":\"verified\",\"isRead\":false,\"alerted\":false,\"dismissed\":false}', 1, 1, 1, '2026-10-04 06:12:39'),
(12, 'disease:141:verified', '{\"eventKey\":\"disease:141:verified\",\"title\":\"Report Verified\",\"description\":\"Your report REF-1791174118649 has been verified.\",\"type\":\"success\",\"time\":\"2:02 PM\",\"createdAt\":\"2026-10-06T06:02:00.980035Z\",\"caseId\":141,\"referenceId\":\"REF-1791174118649\",\"status\":\"verified\",\"isRead\":false,\"alerted\":false,\"dismissed\":false}', 1, 0, 1, '2026-10-06 06:02:01'),
(12, 'disease:152:verified', '{\"eventKey\":\"disease:152:verified\",\"title\":\"Report Verified\",\"description\":\"Your report REF-1791279135949 has been verified.\",\"type\":\"success\",\"time\":\"6:20 PM\",\"createdAt\":\"2026-10-06T10:20:48.682652Z\",\"caseId\":152,\"referenceId\":\"REF-1791279135949\",\"status\":\"verified\",\"isRead\":false,\"alerted\":false,\"dismissed\":false}', 1, 0, 1, '2026-10-06 10:20:48'),
(12, 'general:16:verified', '{\"eventKey\":\"general:16:verified\",\"title\":\"Report Verified\",\"description\":\"Your report PHR-1791177636788 has been verified.\",\"type\":\"success\",\"time\":\"11:07 AM\",\"createdAt\":\"2026-10-07T03:07:33.588964Z\",\"caseId\":null,\"referenceId\":\"PHR-1791177636788\",\"status\":\"verified\",\"isRead\":false,\"alerted\":false,\"dismissed\":false}', 1, 0, 1, '2026-10-07 03:07:33');

-- --------------------------------------------------------

--
-- Table structure for table `planting_harvesting_reports`
--

CREATE TABLE `planting_harvesting_reports` (
  `report_id` int(11) NOT NULL,
  `reference_id` varchar(50) NOT NULL,
  `farmer_id` int(11) NOT NULL,
  `report_type` enum('planting','harvesting','damage','growth') NOT NULL,
  `source` enum('farmer','staff') NOT NULL DEFAULT 'farmer',
  `crop_type` enum('yellow_corn','white_corn','cassava') DEFAULT NULL,
  `description` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `gps_accuracy` decimal(10,2) DEFAULT NULL,
  `altitude` decimal(10,2) DEFAULT NULL,
  `variety` varchar(100) DEFAULT NULL,
  `planting_stage` varchar(50) DEFAULT NULL,
  `area_hectares` decimal(10,2) DEFAULT NULL,
  `privacy_consent` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('received','verified','rejected') NOT NULL DEFAULT 'received',
  `remarks` text DEFAULT NULL,
  `source_report_id` int(11) DEFAULT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `rejected_by` int(11) DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `planting_harvesting_reports`
--

INSERT INTO `planting_harvesting_reports` (`report_id`, `reference_id`, `farmer_id`, `report_type`, `source`, `crop_type`, `description`, `photo`, `latitude`, `longitude`, `gps_accuracy`, `altitude`, `variety`, `planting_stage`, `area_hectares`, `privacy_consent`, `status`, `remarks`, `source_report_id`, `submitted_at`, `verified_at`, `created_at`, `updated_at`, `created_by`, `verified_by`, `rejected_by`, `rejected_at`) VALUES
(7, 'DR-260920-34F918', 5, 'damage', 'staff', NULL, 'qweqwe', 'report_1789873092_6443e7aff3d2.jpg', NULL, 13.3132000, NULL, NULL, NULL, NULL, NULL, 1, 'verified', '', NULL, '2026-09-20 10:58:12', '2026-09-20 10:58:18', '2026-09-20 10:58:12', '2026-09-20 10:58:18', NULL, NULL, NULL, NULL),
(8, 'PH-260920-09D0F4', 6, 'harvesting', 'staff', 'yellow_corn', NULL, 'report_1789873218_c2557350c332.jpg', NULL, NULL, NULL, NULL, 'qwe', 'qwe', 2.00, 1, 'verified', '', NULL, '2026-09-20 11:00:18', '2026-09-20 11:00:25', '2026-09-20 11:00:18', '2026-09-20 11:00:25', NULL, NULL, NULL, NULL),
(9, 'PH-260920-B27644', 5, 'harvesting', 'staff', 'yellow_corn', NULL, 'report_1789873253_5eac7efd3e87.jpg', NULL, NULL, NULL, NULL, 'qwe', 'qwe', 5.00, 1, 'verified', '', NULL, '2026-09-20 11:00:53', '2026-09-20 11:01:03', '2026-09-20 11:00:53', '2026-09-20 11:01:03', NULL, NULL, NULL, NULL),
(10, 'PH-260920-758D91', 2, 'planting', 'staff', 'white_corn', NULL, 'report_1789873522_035412feb37b.jpg', NULL, NULL, NULL, NULL, 'qwe', 'qwe', 2.00, 1, 'verified', '', NULL, '2026-09-20 11:05:22', '2026-09-20 11:05:30', '2026-09-20 11:05:22', '2026-09-20 11:05:30', NULL, NULL, NULL, NULL),
(11, 'PH-260920-324219', 9, 'harvesting', 'staff', 'yellow_corn', NULL, 'report_1789874077_65969b6a73d6.jpg', NULL, NULL, NULL, NULL, 'qwe', 'qweq', 2.00, 1, 'verified', '', NULL, '2026-09-20 11:14:37', '2026-09-20 11:14:45', '2026-09-20 11:14:37', '2026-09-20 11:14:45', NULL, NULL, NULL, NULL),
(12, 'PH-260920-507E71', 6, 'harvesting', 'staff', 'yellow_corn', NULL, NULL, NULL, NULL, NULL, NULL, 'e', 'mature', 5.00, 1, 'rejected', 'Supepr wow', NULL, '2026-09-20 14:50:26', NULL, '2026-09-20 14:50:26', '2026-09-20 14:50:50', 12, NULL, 12, '2026-09-20 14:50:50'),
(13, 'PH-260920-24ACF4', 2, 'harvesting', 'staff', 'yellow_corn', NULL, NULL, NULL, NULL, NULL, NULL, 'qwe', 'qwe', 2.00, 1, 'verified', '', NULL, '2026-09-20 21:04:15', '2026-09-20 21:04:27', '2026-09-20 21:04:15', '2026-09-20 21:04:27', 12, 12, NULL, NULL),
(14, 'DR-260921-399F5E', 5, 'damage', 'staff', NULL, '0 BILISIBILTY', 'report_1789953929_95c2aea5dcd8.jpg', 15.2330000, 123.1233400, NULL, NULL, NULL, NULL, NULL, 1, 'received', '', NULL, '2026-09-21 09:25:29', NULL, '2026-09-21 09:25:29', '2026-09-21 09:25:29', 12, NULL, NULL, NULL),
(15, 'PHR-1791087473358', 12, 'planting', 'farmer', 'yellow_corn', NULL, NULL, NULL, NULL, NULL, NULL, 'Advanta', NULL, 2.00, 1, 'received', NULL, NULL, '2026-10-04 04:17:53', NULL, '2026-10-04 04:17:53', '2026-10-04 04:17:53', NULL, NULL, NULL, NULL),
(16, 'PHR-1791177636788', 12, 'harvesting', 'farmer', 'white_corn', NULL, NULL, NULL, NULL, NULL, NULL, 'Sweet Fortune (Ramigo)', NULL, 1.00, 1, 'verified', '', NULL, '2026-10-05 05:20:36', '2026-10-07 00:18:56', '2026-10-05 05:20:36', '2026-10-07 00:18:56', NULL, 12, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `push_case_state`
--

CREATE TABLE `push_case_state` (
  `case_id` int(11) NOT NULL,
  `status` varchar(64) NOT NULL,
  `revision` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `push_devices`
--

CREATE TABLE `push_devices` (
  `id` bigint(20) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `token` text NOT NULL,
  `farmer_id` int(11) NOT NULL,
  `enabled` tinyint(4) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `push_devices`
--

INSERT INTO `push_devices` (`id`, `token_hash`, `token`, `farmer_id`, `enabled`, `updated_at`) VALUES
(1, '88f7de7cf591894166afa66006a40e3fa44560d6827ef54668fc5ba373b47db3', 'f8ZoZ2EzQmaCcycH6cKJlR:APA91bFRyPNEibO7DMf7gCNf6WeAAvTWD3IcgJ-Mj2z5kqe5L_sd4JpD9L5y7RzsjG_e0Ndo4Y-BpJ48HIU6pwoRSC3SehBAkOCDOKKh9YBPqA4QsPZh_1A', 12, 1, '2026-10-05 04:41:56'),
(8, '19b9e492494c53248c0ec3067f8d8188d97f2840863fa6bb6b16219ae2ac8ece', 'fMilokj4TGGHiYg4jCdgr-:APA91bEIl66_CjDO5UqrWwapzzWKWiQ45ymCjHocxS4z-liD6988PHBHyiHiH3POrfiCBMsqhRQ3Z1wLifgf3OGsuM3Y4d5KYHyPn8Bd8iGM9WoYrvZGZXI', 12, 1, '2026-10-05 04:59:31'),
(10, '5aa3a4989c3a035ed67ec7fb5c1d403cef0e7f23e11c1bee99fad266da300c1f', 'e631YTloR0qmmFZPf25dPA:APA91bFZ4ketv4k_AewuY80q8HCmWMUzBFKkLIqtXeHckiQ3AmF715y7yKTPs_LXvoX6wfWHLCWbKM7b8pQa8E2uRZuzuAW3NWTRdPU6TmTXI7GR7hG3OLg', 12, 1, '2026-10-07 05:10:17'),
(12, 'ffbb2c63f66b252ea21ea63986f4301ff764a47ab6b9103bf626d78edfe9db7b', 'ewzzED3kTDmMYS2OhSiGk1:APA91bHwLKIott9LKtH0GaVB7g9SxBCq17GufRW6R7skoHDZPTxUEKf4st9kmrGbR0-UiKXzUzXpKEJ0lMKhqiEdaasykF_YRm5ifGevcYojH0A2pK3EU5A', 12, 1, '2026-10-07 04:22:09'),
(25, 'a73f180aedb72f36a744d984e3bec89b2d8ae64066fec65e3070aef44ad5c588', 'dcMdeIz7RfuC3EDKykabYJ:APA91bGT6bVAgsJUs75xvdx6U_8EmU1lKQFVuXmPRtum_tAG0p2ccorKErfe85k1mzYzqIrs1BNLuJ4wG_2sK988GTCcJwzVFj22rgX6A9URoxpzswercoA', 12, 1, '2026-10-07 05:43:29');

-- --------------------------------------------------------

--
-- Table structure for table `push_queue`
--

CREATE TABLE `push_queue` (
  `id` bigint(20) NOT NULL,
  `case_id` int(11) NOT NULL,
  `revision` int(11) NOT NULL,
  `device_id` bigint(20) NOT NULL,
  `farmer_id` int(11) NOT NULL,
  `status` varchar(64) NOT NULL,
  `reference_id` varchar(255) NOT NULL,
  `sent_at` datetime DEFAULT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `next_attempt` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `push_sessions`
--

CREATE TABLE `push_sessions` (
  `token_hash` char(64) NOT NULL,
  `farmer_id` int(11) NOT NULL,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `push_sessions`
--

INSERT INTO `push_sessions` (`token_hash`, `farmer_id`, `expires_at`) VALUES
('4652567eddc71f311d1e403de42575d6bf8815cce9b5a81c100f1fa338cde59c', 12, '2026-11-06 05:43:28'),
('5b5aa7d6ac48c5ff9fbf561e62a61fe9489e6fd00a74b01bad3daf87c7d25fe8', 12, '2026-11-04 04:59:31'),
('9cdea894b66b3097adf9602f3978e7ff8a616ea22e8bd17e096a6f5183497181', 12, '2026-11-05 09:45:23'),
('9d8755c79b51ae114bb9c59af2801668d93296964f6d01a3d37d9284d0d5eeb2', 12, '2026-11-04 04:52:15'),
('ef1a178b4a16a6fde7e6c7cffaf1e77d149c9a3d53b919eacfb708a418fbddf6', 12, '2026-11-04 11:49:41'),
('f66f8b9c53ffa6e7ff44e66cca4494dd3594b612d34448a658be3dd62e639c54', 12, '2026-11-05 05:52:39');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `role` enum('agri1','agri2','admin') NOT NULL DEFAULT 'agri1',
  `profile_photo` varchar(255) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `barangay_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `full_name`, `email`, `employee_id`, `role`, `profile_photo`, `phone_number`, `barangay_id`, `status`, `created_at`, `updated_at`) VALUES
(7, 'admin', '$2y$10$088kIj/aseaE78kGEn9vX.tFVxzrhcfyhVWIrSe2fHhV4xCE3p18C', 'Admin User', 'admin@example.com', NULL, 'admin', '', '', NULL, 'active', '2026-07-31 04:48:50', '2026-08-30 05:33:15'),
(8, 'Austriaa', '$2y$10$Xxyrl28C8RspWIm7Zjaky.2864QW5gWKT7KMzPx2QfjyILiQQccKG', 'Leo', 'admin1@casd.local', NULL, 'admin', '1789304018_6aa69cd24ea19.jpg', '0921700436', NULL, 'active', '2026-07-31 05:27:47', '2026-09-13 14:07:34'),
(9, 'JL', '$2y$10$DbM3luWQABACWqHR1KgUoeVaKu7GQ2x12EFvPu0wOnyfPDslLVS3m', 'John Lawrence Dela Cuesta123331233311', 'jldelacuesta009@gmail.com', NULL, 'agri2', NULL, '09217000436aaaa', NULL, 'active', '2026-07-31 05:27:47', '2026-09-13 15:45:21'),
(10, 'admin_test', '$2b$10$wGOErV6zIcIbYhEwPwmh1urEke4GexQLsb633CJ5HZXiMtl81kPre', 'Wag', 'admin_test@casd.local', NULL, 'admin', '', '', NULL, 'active', '2026-07-31 05:54:53', '2026-07-31 05:57:48'),
(11, 'employee_test', '$2b$10$.3UvT7IrWh7jCfEByGQ4W.AEQUOuBnkMX/yYQ5oWsuZ/iMWcdaRIS', 'Test Employee', 'employee_test@casd.local', NULL, 'admin', NULL, NULL, NULL, 'active', '2026-07-31 05:54:53', '2026-08-22 13:02:03'),
(12, 'qweqwe1', '$2y$10$CvPaZrtziKBUaIDo6OotB.WOtc/7PGA0G4qf/ZgBfysIxi4CmtQrC', 'Qweqwe User', 'qweqwe1@casd.local', NULL, 'admin', NULL, NULL, NULL, 'active', '2026-09-13 15:21:42', '2026-09-13 15:21:42');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `barangays`
--
ALTER TABLE `barangays`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `case_messages`
--
ALTER TABLE `case_messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `idx_case_id` (`case_id`);

--
-- Indexes for table `corn_stages`
--
ALTER TABLE `corn_stages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `disease_cases`
--
ALTER TABLE `disease_cases`
  ADD PRIMARY KEY (`case_id`);

--
-- Indexes for table `farmers`
--
ALTER TABLE `farmers`
  ADD PRIMARY KEY (`farmer_id`);

--
-- Indexes for table `notification_state`
--
ALTER TABLE `notification_state`
  ADD PRIMARY KEY (`farmer_id`,`event_key`);

--
-- Indexes for table `planting_harvesting_reports`
--
ALTER TABLE `planting_harvesting_reports`
  ADD PRIMARY KEY (`report_id`),
  ADD UNIQUE KEY `reference_id` (`reference_id`),
  ADD KEY `fk_ph_report_farmer` (`farmer_id`);

--
-- Indexes for table `push_case_state`
--
ALTER TABLE `push_case_state`
  ADD PRIMARY KEY (`case_id`);

--
-- Indexes for table `push_devices`
--
ALTER TABLE `push_devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`);

--
-- Indexes for table `push_queue`
--
ALTER TABLE `push_queue`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_device` (`case_id`,`revision`,`device_id`);

--
-- Indexes for table `push_sessions`
--
ALTER TABLE `push_sessions`
  ADD PRIMARY KEY (`token_hash`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `case_messages`
--
ALTER TABLE `case_messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=108;

--
-- AUTO_INCREMENT for table `corn_stages`
--
ALTER TABLE `corn_stages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `disease_cases`
--
ALTER TABLE `disease_cases`
  MODIFY `case_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=160;

--
-- AUTO_INCREMENT for table `farmers`
--
ALTER TABLE `farmers`
  MODIFY `farmer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `planting_harvesting_reports`
--
ALTER TABLE `planting_harvesting_reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `push_devices`
--
ALTER TABLE `push_devices`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `push_queue`
--
ALTER TABLE `push_queue`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `planting_harvesting_reports`
--
ALTER TABLE `planting_harvesting_reports`
  ADD CONSTRAINT `fk_ph_report_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `farmers` (`farmer_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
