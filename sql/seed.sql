USE sigel;

START TRANSACTION;

INSERT INTO roles (id, nombre) VALUES
(1, 'administrador'),
(2, 'operador');

INSERT INTO usuarios (id, rol_id, nombre, correo, password_hash) VALUES
(
    1,
    1,
    'Administrador de Sistema',
    'admin@sigel.utn.ac.cr',
    '$2y$10$IIsvnoo63JGz08Bqm9Jhj.FtR5uceJehGpAeX0Jv1VSS.FCZnA.XG'
),
(
    2,
    2,
    'operador Principal',
    'operador@sigel.utn.ac.cr',
    '$2y$10$MrJ8tiPLn/4ScVfecUc5H.MTZH3BNvngbd1HfH.R1Vvs6RFtVgFJ6'
),
(
    3,
    2,
    'operador Secundario',
    'operador2@sigel.utn.ac.cr',
    '$2y$10$R4RmGB4OH/6E/6icAN50XeOBJoqUwuDBoGMs4aALtA3Gxrk0K/9Ky'
);

INSERT INTO categorias (id, nombre, dias_max_prestamo) VALUES
(1, 'Computadoras', 7),
(2, 'Proyectores', 3);

INSERT INTO equipos (categoria_id, codigo, nombre) VALUES
(1, 'COM-001', 'Computadora portátil 1'),
(1, 'COM-002', 'Computadora portátil 2'),
(2, 'PRO-001', 'Proyector 1');

COMMIT;