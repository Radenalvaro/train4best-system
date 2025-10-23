-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- <CHANGE> Removed CREATE DATABASE statement - database must exist first
USE `train4best_db`;

-- --------------------------------------------------------
-- Struktur dari tabel `attendance`
-- --------------------------------------------------------

CREATE TABLE `attendance` (
  `id` int NOT NULL,
  `report_id` int DEFAULT NULL,
  `participant_id` int DEFAULT NULL,
  `attendance_date` date DEFAULT NULL,
  `status` enum('HADIR','TIDAK HADIR') DEFAULT 'HADIR'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `attendance` (`id`, `report_id`, `participant_id`, `attendance_date`, `status`) VALUES
(348, 16, 142, '2025-08-01', 'HADIR'),
(349, 16, 142, '2025-08-02', 'HADIR'),
(350, 16, 143, '2025-08-01', 'HADIR'),
(351, 16, 143, '2025-08-02', 'HADIR'),
(352, 16, 144, '2025-08-01', 'HADIR'),
(353, 16, 144, '2025-08-02', 'HADIR'),
(354, 16, 145, '2025-08-01', 'HADIR'),
(355, 16, 145, '2025-08-02', 'HADIR'),
(356, 16, 146, '2025-08-01', 'HADIR'),
(357, 16, 146, '2025-08-02', 'HADIR'),
(358, 16, 147, '2025-08-01', 'HADIR'),
(359, 16, 147, '2025-08-02', 'HADIR'),
(360, 16, 148, '2025-08-01', 'HADIR'),
(361, 16, 148, '2025-08-02', 'HADIR');

-- --------------------------------------------------------
-- Struktur dari tabel `attendance_dates`
-- --------------------------------------------------------

CREATE TABLE `attendance_dates` (
  `id` int NOT NULL,
  `report_id` int DEFAULT NULL,
  `attendance_date` date DEFAULT NULL,
  `day_name` varchar(20) DEFAULT NULL,
  `sort_order` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `attendance_dates` (`id`, `report_id`, `attendance_date`, `day_name`, `sort_order`) VALUES
(151, 16, '2025-08-01', 'selasa', 0),
(152, 16, '2025-08-02', 'rabu', 1);

-- --------------------------------------------------------
-- Struktur dari tabel `berita_acara`
-- --------------------------------------------------------

CREATE TABLE `berita_acara` (
  `id` int NOT NULL,
  `report_id` int DEFAULT NULL,
  `content` text,
  `participant_mode` enum('online','offline','hybrid') DEFAULT 'offline',
  `trainer_name` varchar(255) DEFAULT NULL,
  `training_mode` enum('online','offline') DEFAULT 'offline',
  `trainer_description` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `berita_acara` (`id`, `report_id`, `content`, `participant_mode`, `trainer_name`, `training_mode`, `trainer_description`) VALUES
(76, 16, 'alalalalla', 'online', 'mrs andrea', 'offline', '');

-- --------------------------------------------------------
-- Struktur dari tabel `documentation_sections`
-- --------------------------------------------------------

CREATE TABLE `documentation_sections` (
  `id` int NOT NULL,
  `report_id` int DEFAULT NULL,
  `section_date` date DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `sort_order` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `documentation_sections` (`id`, `report_id`, `section_date`, `location`, `sort_order`) VALUES
(162, 16, '2025-08-01', 'jakarta', 0),
(163, 16, '2025-08-02', 'jakarta', 1);

-- --------------------------------------------------------
-- Struktur dari tabel `participants`
-- --------------------------------------------------------

CREATE TABLE `participants` (
  `id` int NOT NULL,
  `report_id` int DEFAULT NULL,
  `participant_name` varchar(255) NOT NULL,
  `institution` varchar(255) NOT NULL,
  `sort_order` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `participants` (`id`, `report_id`, `participant_name`, `institution`, `sort_order`) VALUES
(142, 16, 'Raden Hayfa Ghazy Alvaro', 'SMK Bakti Idhata', 0),
(143, 16, 'lala', 'SMK Bakti Idhata', 1),
(144, 16, 'lulu', 'SMK Bakti Idhata', 2),
(145, 16, 'lolo', 'SMK Bakti Idhata', 3),
(146, 16, 'riri', 'SMK Bakti Idhata', 4),
(147, 16, 'pororo', 'SMK Bakti Idhata', 5),
(148, 16, 'gotre', 'SMK Bakti Idhata', 6);

-- --------------------------------------------------------
-- Struktur dari tabel `scores`
-- --------------------------------------------------------

CREATE TABLE `scores` (
  `id` int NOT NULL,
  `report_id` int DEFAULT NULL,
  `participant_id` int DEFAULT NULL,
  `pretest_score` decimal(5,2) DEFAULT NULL,
  `posttest_score` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `scores` (`id`, `report_id`, `participant_id`, `pretest_score`, `posttest_score`) VALUES
(135, 16, 142, 0.00, 0.00),
(136, 16, 143, 0.00, 0.00),
(137, 16, 144, 0.00, 0.00),
(138, 16, 145, 0.00, 0.00),
(139, 16, 146, 0.00, 0.00),
(140, 16, 147, 0.00, 0.00),
(141, 16, 148, 0.00, 0.00);

-- --------------------------------------------------------
-- Struktur dari tabel `training_reports`
-- --------------------------------------------------------

CREATE TABLE `training_reports` (
  `id` int NOT NULL,
  `report_name` varchar(255) NOT NULL,
  `pdf_filename` varchar(255) NOT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `training_title` varchar(255) DEFAULT NULL,
  `training_date_start` date DEFAULT NULL,
  `training_date_end` date DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `training_reports` (`id`, `report_name`, `pdf_filename`, `cover_image`, `training_title`, `training_date_start`, `training_date_end`, `created_by`, `created_at`, `updated_at`) VALUES
(16, 'Pemograman pemula', 'Laporan_Pemograman_pemula.pdf', 'cover_68bf9a7418fe6_1757387380.png', 'minimal pc sekolah itu gak putus internetnya', '2025-08-10', '2025-08-03', 1, '2025-09-09 03:09:40', '2025-09-10 04:17:16');

-- --------------------------------------------------------
-- Struktur dari tabel `uploaded_files`
-- --------------------------------------------------------

CREATE TABLE `uploaded_files` (
  `id` int NOT NULL,
  `report_id` int DEFAULT NULL,
  `file_type` enum('cover','participant_scan','instructor_scan','schedule_image','pretest_image','posttest_image','documentation','syllabus','certificate') NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `upload_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `section_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO `uploaded_files` (`id`, `report_id`, `file_type`, `file_name`, `file_path`, `upload_date`, `section_id`) VALUES
(305, 16, 'cover', 'LAPORAN (1).png', '../uploads/covers/cover_68bf9a7418fe6_1757387380.png', '2025-09-09 03:09:40', NULL),
(306, 16, 'participant_scan', 'Desain tanpa judul.png', '../uploads/participant_scans/participant_scan_68bf9a741b1c9_1757387380.png', '2025-09-09 03:09:40', 0),
(307, 16, 'instructor_scan', 'flowchart_wokwi.png', '../uploads/instructor_scans/instructor_scan_68bf9a741cb8e_1757387380.png', '2025-09-09 03:09:40', 0),
(308, 16, 'schedule_image', 'pexels-shafi_fotumcatcher-1249695-2378278.jpg', '../uploads/schedules/schedule_image_68bf9a741e2a4_1757387380.jpg', '2025-09-09 03:09:40', 0),
(309, 16, 'pretest_image', 'pexels-shafi_fotumcatcher-1249695-2378278.jpg', '../uploads/pre