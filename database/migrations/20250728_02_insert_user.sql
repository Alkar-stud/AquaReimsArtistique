-- Ajout du super admin
INSERT IGNORE INTO `user` (`id`, `username`, `password`, `email`, `role`) VALUES
(1, 'Aradmin', 'MdpBidonAChangerAlInstall', 'example@email.com', 1);
