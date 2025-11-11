-- Ajout des role
INSERT IGNORE INTO `role` (`label`, `level`) VALUES
    ( 'Super administrateur', 0),
    ( 'Administrateur', 1),
    ( 'Bureau', 2),
    ( 'CoDir', 3),
    ( 'Bénévole', 4);
