-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 02-10-2025 a las 01:36:46
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `scyberrotsen`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `accesorio`
--

CREATE TABLE `accesorio` (
  `Id_Accesorios` int(11) NOT NULL,
  `Nombre` varchar(50) NOT NULL,
  `Marca` varchar(30) NOT NULL,
  `Modelo` varchar(30) NOT NULL,
  `Precio` decimal(10,2) DEFAULT NULL CHECK (`Precio` >= 0),
  `stockDisponible` int(11) DEFAULT NULL CHECK (`stockDisponible` >= 0),
  `Proveedor` varchar(50) NOT NULL,
  `Codigo` varchar(30) NOT NULL,
  `Presentacion_Producto` varchar(50) NOT NULL,
  `DiaIngreso` int(11) NOT NULL,
  `MesIngreso` int(11) NOT NULL,
  `AnioIngreso` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `accesorio`
--

INSERT INTO `accesorio` (`Id_Accesorios`, `Nombre`, `Marca`, `Modelo`, `Precio`, `stockDisponible`, `Proveedor`, `Codigo`, `Presentacion_Producto`, `DiaIngreso`, `MesIngreso`, `AnioIngreso`) VALUES
(5, 'Mouse', 'qwe', 'zxc', 12.00, 5, 'ads', '12345789', 'Unidad', 1, 10, 2025);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `escaner`
--

CREATE TABLE `escaner` (
  `Escanner_Id` int(11) NOT NULL,
  `Id_Servicio` int(11) NOT NULL,
  `PrecioTotal` decimal(10,2) NOT NULL,
  `TipoDocumento` varchar(20) NOT NULL,
  `Entregado` tinyint(1) NOT NULL,
  `MontoPago` decimal(10,2) NOT NULL,
  `DiaEntrega` int(11) NOT NULL,
  `MesEntrega` int(11) NOT NULL,
  `AnioEntrega` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `escaner`
--

INSERT INTO `escaner` (`Escanner_Id`, `Id_Servicio`, `PrecioTotal`, `TipoDocumento`, `Entregado`, `MontoPago`, `DiaEntrega`, `MesEntrega`, `AnioEntrega`) VALUES
(7, 19, 12.00, 'Word', 1, 12.00, 2, 10, 2025);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `impresion`
--

CREATE TABLE `impresion` (
  `Impresion_Id` int(11) NOT NULL,
  `Id_Servicio` int(11) NOT NULL,
  `NomDoc` text NOT NULL,
  `Color` tinyint(1) NOT NULL,
  `tipoDocumento` varchar(50) NOT NULL,
  `numCopias` int(11) NOT NULL,
  `PrecioTotal` decimal(10,2) NOT NULL,
  `Entregado` tinyint(1) NOT NULL,
  `MontoPago` decimal(10,2) NOT NULL,
  `DiaEntrega` int(11) NOT NULL,
  `MesEntrega` int(11) NOT NULL,
  `AnioEntrega` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `impresion`
--

INSERT INTO `impresion` (`Impresion_Id`, `Id_Servicio`, `NomDoc`, `Color`, `tipoDocumento`, `numCopias`, `PrecioTotal`, `Entregado`, `MontoPago`, `DiaEntrega`, `MesEntrega`, `AnioEntrega`) VALUES
(6, 17, 'asd', 0, 'PDF', 1, 12.00, 1, 12.00, 10, 10, 2025),
(7, 21, 'asd', 0, 'PDF', 1, 12.00, 1, 12.00, 10, 10, 2025);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol`
--

CREATE TABLE `rol` (
  `Id_Rol` int(11) NOT NULL,
  `TipoRol` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `rol`
--

INSERT INTO `rol` (`Id_Rol`, `TipoRol`) VALUES
(0, 'Normal'),
(1, 'Admin');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `servicio`
--

CREATE TABLE `servicio` (
  `nombreServicio` varchar(50) NOT NULL,
  `User_Id` int(11) NOT NULL,
  `Descripcion` text NOT NULL,
  `Id_Servicio` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `servicio`
--

INSERT INTO `servicio` (`nombreServicio`, `User_Id`, `Descripcion`, `Id_Servicio`) VALUES
('Venta de Accesorios', 1, 'Venta Accesorio', 1),
('Venta de Accesorios', 1, 'Venta Accesorio, Total: $12.00', 2),
('Escaneo - PDF', 1, 'asd', 3),
('Escaneo - Word', 1, '21e, Entregado', 4),
('Escaneo - Word', 1, 'asd, Entregado', 5),
('Impresión - Word', 1, 'weq, Entregado', 6),
('Impresión - Word', 1, 'we2, Entregado', 7),
('Impresión - Word', 1, 'we2, Sin Entregar', 8),
('Escaneo - Imagen', 1, 'asd - Entrega: 2025-10-09, Sin Entregar', 9),
('Impresión - PDF', 1, 'asd - Entrega: 2025-09-30, Sin Entregar', 10),
('Venta de Accesorios', 1, 'Venta Accesorio', 11),
('Venta de Accesorios', 1, 'Venta Accesorio', 12),
('Escaneo - PDF', 1, 'Escaneo', 13),
('Escaneo - Word', 1, 'wa', 14),
('Impresión - PDF', 1, '2qwsa', 15),
('Venta de Accesorios', 1, 'Venta Accesorio, Total: $12.00', 16),
('Impresión - PDF', 1, 'sad', 17),
('Escaneo - Word', 1, 'adss', 19),
('Impresión - PDF', 1, 'asdq', 21);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `User_Id` int(11) NOT NULL,
  `Usuario` varchar(50) NOT NULL,
  `Contrasena` varchar(255) NOT NULL,
  `Correo` varchar(255) NOT NULL,
  `Id_Rol` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`User_Id`, `Usuario`, `Contrasena`, `Correo`, `Id_Rol`) VALUES
(1, 'Admin', '$2y$10$kevtxiADun0yC3TcJsztI.PtbP8c9hb2LG42N9tKsYDUzX4ijacTu', 'baevaj@naver.com', 1),
(126, 'test', '$2y$10$qygKx3r.7WckUJujrMssSONdOF/TnnL4S0AHHcWZYxY55KegouJjq', 'aw@as.com', 0),
(127, 'test1', '$2y$10$osG07F2EXoxR3rRIryvi6u8jNWB03DNe1COE6IL8Q3nxqNrB1eMvu', 'asd@sdaokm.com', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `venta`
--

CREATE TABLE `venta` (
  `Id_Venta` int(11) NOT NULL,
  `montoVenta` decimal(10,2) DEFAULT NULL,
  `MontoPago` decimal(10,2) NOT NULL,
  `Id_Servicio` int(11) DEFAULT NULL,
  `DiaVenta` int(11) NOT NULL,
  `MesVenta` int(11) NOT NULL,
  `AnioVenta` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `venta`
--

INSERT INTO `venta` (`Id_Venta`, `montoVenta`, `MontoPago`, `Id_Servicio`, `DiaVenta`, `MesVenta`, `AnioVenta`) VALUES
(6, 12.00, 13.00, 16, 1, 10, 2025);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventaaccesorios`
--

CREATE TABLE `ventaaccesorios` (
  `Id_Venta` int(11) NOT NULL,
  `Id_Accesorios` int(11) NOT NULL,
  `cantidad` int(11) DEFAULT NULL CHECK (`cantidad` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ventaaccesorios`
--

INSERT INTO `ventaaccesorios` (`Id_Venta`, `Id_Accesorios`, `cantidad`) VALUES
(6, 5, 1);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `accesorio`
--
ALTER TABLE `accesorio`
  ADD PRIMARY KEY (`Id_Accesorios`);

--
-- Indices de la tabla `escaner`
--
ALTER TABLE `escaner`
  ADD PRIMARY KEY (`Escanner_Id`),
  ADD KEY `Id_Servicio` (`Id_Servicio`);

--
-- Indices de la tabla `impresion`
--
ALTER TABLE `impresion`
  ADD PRIMARY KEY (`Impresion_Id`),
  ADD KEY `Id_Servicio` (`Id_Servicio`);

--
-- Indices de la tabla `rol`
--
ALTER TABLE `rol`
  ADD PRIMARY KEY (`Id_Rol`);

--
-- Indices de la tabla `servicio`
--
ALTER TABLE `servicio`
  ADD PRIMARY KEY (`Id_Servicio`),
  ADD KEY `User_Id` (`User_Id`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`User_Id`),
  ADD KEY `Id_Rol` (`Id_Rol`);

--
-- Indices de la tabla `venta`
--
ALTER TABLE `venta`
  ADD PRIMARY KEY (`Id_Venta`),
  ADD KEY `fk_venta_servicio` (`Id_Servicio`);

--
-- Indices de la tabla `ventaaccesorios`
--
ALTER TABLE `ventaaccesorios`
  ADD KEY `Id_Accesorios` (`Id_Accesorios`),
  ADD KEY `Id_Venta` (`Id_Venta`),
  ADD KEY `idx_venta_accesorios` (`Id_Venta`,`Id_Accesorios`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `accesorio`
--
ALTER TABLE `accesorio`
  MODIFY `Id_Accesorios` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `escaner`
--
ALTER TABLE `escaner`
  MODIFY `Escanner_Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `impresion`
--
ALTER TABLE `impresion`
  MODIFY `Impresion_Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `servicio`
--
ALTER TABLE `servicio`
  MODIFY `Id_Servicio` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `User_Id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=128;

--
-- AUTO_INCREMENT de la tabla `venta`
--
ALTER TABLE `venta`
  MODIFY `Id_Venta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `escaner`
--
ALTER TABLE `escaner`
  ADD CONSTRAINT `escaner_ibfk_1` FOREIGN KEY (`Id_Servicio`) REFERENCES `servicio` (`Id_Servicio`);

--
-- Filtros para la tabla `impresion`
--
ALTER TABLE `impresion`
  ADD CONSTRAINT `impresion_ibfk_1` FOREIGN KEY (`Id_Servicio`) REFERENCES `servicio` (`Id_Servicio`);

--
-- Filtros para la tabla `servicio`
--
ALTER TABLE `servicio`
  ADD CONSTRAINT `servicio_ibfk_1` FOREIGN KEY (`User_Id`) REFERENCES `users` (`User_Id`);

--
-- Filtros para la tabla `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`Id_Rol`) REFERENCES `rol` (`Id_Rol`);

--
-- Filtros para la tabla `venta`
--
ALTER TABLE `venta`
  ADD CONSTRAINT `venta_ibfk_1` FOREIGN KEY (`Id_Servicio`) REFERENCES `servicio` (`Id_Servicio`);

--
-- Filtros para la tabla `ventaaccesorios`
--
ALTER TABLE `ventaaccesorios`
  ADD CONSTRAINT `ventaaccesorios_ibfk_1` FOREIGN KEY (`Id_Venta`) REFERENCES `venta` (`Id_Venta`),
  ADD CONSTRAINT `ventaaccesorios_ibfk_2` FOREIGN KEY (`Id_Accesorios`) REFERENCES `accesorio` (`Id_Accesorios`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
