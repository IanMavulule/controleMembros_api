-- Importação: Culto de Domingo de 15/11/2026 (programa id = 1)
-- Origem: REGISTO DE MEMBROS DO CULTO (respostas).xlsx
-- Linhas no ficheiro: 393 | presentes: 381 (278 com número, 103 sem número) | repetições da mesma pessoa: 12
-- Regras do sistema aplicadas:
--   * com número: o número identifica a pessoa; se já existir, é reaproveitada (nome e bairro não mudam);
--   * sem número (ou número inválido): é sempre registada como pessoa nova;
--   * zonas/bairros comparados pelo code (sem acentos, maiúsculas ou espaços); os existentes são reaproveitados;
--   * quem chegar a 5 cultos passa a membro.
-- Requer a migração make_phone_nullable_on_people_table (people.phone opcional).
-- Executar uma única vez: cada execução cria um novo culto e novas pessoas sem número.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
START TRANSACTION;

-- Autor dos registos: o primeiro administrador.
SET @author_id := (SELECT id FROM users WHERE role = 'admin' ORDER BY id LIMIT 1);

-- 1. Culto (finalizado às 15:00)
INSERT INTO worships (program_id, name, title, date, start_time, end_time, created_by, created_at, updated_at)
VALUES (1, 'Culto de Domingo', NULL, '2026-11-15', '08:00:00', '15:00:00', @author_id, NOW(), NOW());
SET @worship_id := LAST_INSERT_ID();

-- 2. Zonas / bairros (80); os que já existem são ignorados
INSERT IGNORE INTO locations (name, code, created_at, updated_at) VALUES
  ('1 Rotunda', '1-rotunda', NOW(), NOW()),
  ('1º de Maio', '1o-de-maio', NOW(), NOW()),
  ('25 de Junho', '25-de-junho', NOW(), NOW()),
  ('Agostinho Neto', 'agostinho-neto', NOW(), NOW()),
  ('Albazine', 'albazine', NOW(), NOW()),
  ('Alto-Maé', 'alto-mae', NOW(), NOW()),
  ('Alto-Maé A', 'alto-mae-a', NOW(), NOW()),
  ('Av. Angola', 'av-angola', NOW(), NOW()),
  ('Bagamoio', 'bagamoio', NOW(), NOW()),
  ('Benfica', 'benfica', NOW(), NOW()),
  ('Bobole', 'bobole', NOW(), NOW()),
  ('Boquisso', 'boquisso', NOW(), NOW()),
  ('Casa Branca', 'casa-branca', NOW(), NOW()),
  ('Central C', 'central-c', NOW(), NOW()),
  ('Chamanculo', 'chamanculo', NOW(), NOW()),
  ('Chamanculo C', 'chamanculo-c', NOW(), NOW()),
  ('Choupal', 'choupal', NOW(), NOW()),
  ('Fajardo', 'fajardo', NOW(), NOW()),
  ('Ferroviário', 'ferroviario', NOW(), NOW()),
  ('Fomento Cial', 'fomento-cial', NOW(), NOW()),
  ('Gaza', 'gaza', NOW(), NOW()),
  ('George Dimitrov', 'george-dimitrov', NOW(), NOW()),
  ('Habel Jafar', 'habel-jafar', NOW(), NOW()),
  ('Hulene A', 'hulene-a', NOW(), NOW()),
  ('Hulene B', 'hulene-b', NOW(), NOW()),
  ('Infulene', 'infulene', NOW(), NOW()),
  ('Infulene D', 'infulene-d', NOW(), NOW()),
  ('Intaka', 'intaka', NOW(), NOW()),
  ('Intaka 2', 'intaka-2', NOW(), NOW()),
  ('Jardim', 'jardim', NOW(), NOW()),
  ('Khongolote', 'khongolote', NOW(), NOW()),
  ('Kumbeza', 'kumbeza', NOW(), NOW()),
  ('Laulane', 'laulane', NOW(), NOW()),
  ('Liberdade', 'liberdade', NOW(), NOW()),
  ('Luís Cabral', 'luis-cabral', NOW(), NOW()),
  ('Macaneta', 'macaneta', NOW(), NOW()),
  ('Machaquene C', 'machaquene-c', NOW(), NOW()),
  ('Machava', 'machava', NOW(), NOW()),
  ('Machava Bedene', 'machava-bedene', NOW(), NOW()),
  ('Machava Km15', 'machava-km15', NOW(), NOW()),
  ('Mafalala', 'mafalala', NOW(), NOW()),
  ('Magoanine', 'magoanine', NOW(), NOW()),
  ('Magoanine A', 'magoanine-a', NOW(), NOW()),
  ('Magoanine B', 'magoanine-b', NOW(), NOW()),
  ('Magoanine C', 'magoanine-c', NOW(), NOW()),
  ('Magoanine CMC', 'magoanine-cmc', NOW(), NOW()),
  ('Mahotas', 'mahotas', NOW(), NOW()),
  ('Malhangalene', 'malhangalene', NOW(), NOW()),
  ('Malhazine', 'malhazine', NOW(), NOW()),
  ('Maluana', 'maluana', NOW(), NOW()),
  ('Marracuene', 'marracuene', NOW(), NOW()),
  ('Mateke', 'mateke', NOW(), NOW()),
  ('Matendene', 'matendene', NOW(), NOW()),
  ('Mathlemele', 'mathlemele', NOW(), NOW()),
  ('Matola', 'matola', NOW(), NOW()),
  ('Matola Rio', 'matola-rio', NOW(), NOW()),
  ('Matolagar', 'matolagar', NOW(), NOW()),
  ('Michafutene', 'michafutene', NOW(), NOW()),
  ('Missão Roque', 'missao-roque', NOW(), NOW()),
  ('Mucupe', 'mucupe', NOW(), NOW()),
  ('Muhalaze', 'muhalaze', NOW(), NOW()),
  ('Mumemo', 'mumemo', NOW(), NOW()),
  ('Munhana', 'munhana', NOW(), NOW()),
  ('Ndlavela', 'ndlavela', NOW(), NOW()),
  ('Patrice', 'patrice', NOW(), NOW()),
  ('Polana Caniço', 'polana-canico', NOW(), NOW()),
  ('Pussulane', 'pussulane', NOW(), NOW()),
  ('Romão', 'romao', NOW(), NOW()),
  ('Santa Isabel', 'santa-isabel', NOW(), NOW()),
  ('São Dâmaso', 'sao-damaso', NOW(), NOW()),
  ('Somachild', 'somachild', NOW(), NOW()),
  ('T-3', 't-3', NOW(), NOW()),
  ('Tenga', 'tenga', NOW(), NOW()),
  ('Triunfo', 'triunfo', NOW(), NOW()),
  ('Tsalala', 'tsalala', NOW(), NOW()),
  ('Urbanização', 'urbanizacao', NOW(), NOW()),
  ('Xipamanine', 'xipamanine', NOW(), NOW()),
  ('Xiquelene', 'xiquelene', NOW(), NOW()),
  ('Zimpeto', 'zimpeto', NOW(), NOW()),
  ('Zona Verde', 'zona-verde', NOW(), NOW());

-- 3. Pessoas com número (278), numa tabela temporária
DROP TEMPORARY TABLE IF EXISTS import_people;
CREATE TEMPORARY TABLE import_people (
  name VARCHAR(255) NOT NULL,
  surname VARCHAR(255) NOT NULL,
  phone VARCHAR(20) NOT NULL PRIMARY KEY,
  gender VARCHAR(10) NOT NULL,
  location_code VARCHAR(255) NULL,
  registered_at DATETIME NOT NULL
) DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO import_people (name, surname, phone, gender, location_code, registered_at) VALUES
  ('Almerindo', 'Euclides Pacule', '+258845100992', 'male', 'zimpeto', '2026-11-15 08:12:39'),
  ('Xavier', 'Fumo', '+258840000001', 'male', 'khongolote', '2026-11-15 08:14:04'),
  ('Vitoria', 'Denisee', '+258878637783', 'female', 'polana-canico', '2026-11-15 09:03:34'),
  ('Virgínia', 'Timane', '+258877786130', 'female', 'marracuene', '2026-11-15 09:04:53'),
  ('Pelússio', 'Cossa', '+258877345811', 'male', 'zimpeto', '2026-11-15 09:06:33'),
  ('Lúcia', 'Inguane', '+258847518999', 'female', 'ferroviario', '2026-11-15 09:07:11'),
  ('Edna', 'Veterano', '+258847408120', 'female', 'mahotas', '2026-11-15 09:12:35'),
  ('Sara', 'Bie', '+258875178018', 'female', 'michafutene', '2026-11-15 09:13:55'),
  ('Ângelo', 'Veterano', '+258843331405', 'male', 'mahotas', '2026-11-15 09:15:34'),
  ('Jenny', 'Macie', '+258848848257', 'female', 'luis-cabral', '2026-11-15 09:16:14'),
  ('Alcinda', 'Jeque', '+258843603241', 'female', 'av-angola', '2026-11-15 09:19:57'),
  ('Natércia', 'Tchambule', '+258842659023', 'female', 'magoanine-cmc', '2026-11-15 09:21:51'),
  ('Ricardo', 'Buque', '+258850159265', 'male', '1o-de-maio', '2026-11-15 09:22:30'),
  ('Crimilde', 'Edna', '+258844745094', 'female', 'albazine', '2026-11-15 09:27:17'),
  ('Júlia', 'Watch', '+258846799055', 'female', 'zona-verde', '2026-11-15 09:29:00'),
  ('Priscila', 'Chilengue', '+258877187899', 'female', 'zona-verde', '2026-11-15 09:33:25'),
  ('Jasmin', 'Macuacua', '+258860423608', 'female', 'somachild', '2026-11-15 09:34:32'),
  ('Assucênia', 'Lote', '+258820052040', 'female', 'somachild', '2026-11-15 09:35:20'),
  ('Benildo', 'Massanganhe', '+258877620111', 'male', 'khongolote', '2026-11-15 09:36:06'),
  ('Edgar', 'Ndlate', '+258843876913', 'male', 't-3', '2026-11-15 09:36:51'),
  ('Eduardo', 'Mussa', '+258879024720', 'male', 'mahotas', '2026-11-15 09:37:25'),
  ('Gilda', 'Chissano', '+258847917772', 'female', 'intaka-2', '2026-11-15 09:38:53'),
  ('Cátia', 'Macamo', '+258869871330', 'female', 'xipamanine', '2026-11-15 09:40:22'),
  ('Adnércia', 'Cossa', '+258843493175', 'female', 'gaza', '2026-11-15 09:41:32'),
  ('Daniel', 'Joaquim', '+258880512700', 'male', 'george-dimitrov', '2026-11-15 09:43:23'),
  ('Célia', 'Chambale', '+258862384911', 'female', 't-3', '2026-11-15 09:44:12'),
  ('Ana', 'Mutisse', '+258840486415', 'female', 'agostinho-neto', '2026-11-15 09:45:10'),
  ('Armindo', 'Cau', '+258866177140', 'male', 'zona-verde', '2026-11-15 09:46:51'),
  ('Nelson', 'Bambo', '+258845204524', 'male', 'habel-jafar', '2026-11-15 09:47:52'),
  ('Helena', 'Mangana', '+258862343361', 'female', 'bagamoio', '2026-11-15 09:48:45'),
  ('Judite', 'Manhiça', '+258847724563', 'female', 'marracuene', '2026-11-15 09:49:32'),
  ('Silvia', 'Cuna', '+258841587474', 'female', 'hulene-b', '2026-11-15 09:50:13'),
  ('Alzira', 'Miguel', '+258844738855', 'female', 'zona-verde', '2026-11-15 09:51:20'),
  ('Diana', 'Tune', '+258876085682', 'female', 'intaka-2', '2026-11-15 09:52:53'),
  ('Lourenço', 'Tune', '+258842505000', 'male', 'intaka-2', '2026-11-15 09:53:22'),
  ('Rossana', 'Bay', '+258845431006', 'female', 'marracuene', '2026-11-15 09:54:07'),
  ('Daniela', 'Cassimo', '+258848837274', 'female', 'mahotas', '2026-11-15 09:54:41'),
  ('Ana', 'Cubula', '+258848286288', 'female', 'ndlavela', '2026-11-15 09:55:12'),
  ('Lúcia', 'Chivinde', '+258875037857', 'female', 'zona-verde', '2026-11-15 09:55:49'),
  ('Laura', 'Madonda', '+258848672684', 'female', 'zona-verde', '2026-11-15 09:56:34'),
  ('Onésia', 'Mbalate', '+258865234289', 'female', 'gaza', '2026-11-15 09:57:22'),
  ('Elia', 'Nhanala', '+258844284962', 'female', 'ndlavela', '2026-11-15 09:58:04'),
  ('Latinha', 'Branquinho', '+258861537718', 'female', 'urbanizacao', '2026-11-15 09:59:05'),
  ('Luís', 'Chembene', '+258868532746', 'male', 'tsalala', '2026-11-15 09:59:38'),
  ('Marta', 'Machava', '+258879545200', 'female', 'tsalala', '2026-11-15 10:00:09'),
  ('Érica', 'Fanequisso', '+258848344687', 'female', 'khongolote', '2026-11-15 10:00:59'),
  ('Geltina', 'Macie', '+258873812159', 'female', 'khongolote', '2026-11-15 10:01:39'),
  ('Roberto', 'Emílio', '+258862708497', 'male', 'marracuene', '2026-11-15 10:02:27'),
  ('Nelson', 'Machonhane', '+258849645130', 'male', '1o-de-maio', '2026-11-15 10:03:17'),
  ('Leonardo', 'Mathe', '+258842042316', 'male', 'marracuene', '2026-11-15 10:03:50'),
  ('Nanci', 'de Jesus', '+258869936190', 'female', 'george-dimitrov', '2026-11-15 10:05:23'),
  ('Nayra', 'Alberto', '+258879306073', 'female', 'zona-verde', '2026-11-15 10:06:13'),
  ('Giovana', 'Magaia', '+258867399379', 'female', 'zona-verde', '2026-11-15 10:07:34'),
  ('Neusa', 'Dimas', '+258879074920', 'female', 'khongolote', '2026-11-15 10:08:13'),
  ('Harazaque', 'Mahumede', '+258845032260', 'female', 'patrice', '2026-11-15 10:09:21'),
  ('Meri', 'Chimusse', '+258843430171', 'female', 'gaza', '2026-11-15 10:10:04'),
  ('Palmira', 'Guilegue', '+258863937351', 'female', 'mathlemele', '2026-11-15 10:11:07'),
  ('Gabriel', 'Julio', '+258862662897', 'male', 'magoanine', '2026-11-15 10:11:51'),
  ('João', 'Sumbane', '+258867736673', 'male', 'bagamoio', '2026-11-15 10:12:31'),
  ('Carla', 'Nhantumbo', '+258849059494', 'female', 'hulene-b', '2026-11-15 10:13:11'),
  ('Armindo', 'Chidere', '+258875027325', 'male', 'sao-damaso', '2026-11-15 10:14:46'),
  ('Sara', 'Matsimbe', '+258867463241', 'female', 'malhazine', '2026-11-15 10:16:56'),
  ('Isabel', 'Seifane', '+258840733045', 'female', 'matola', '2026-11-15 10:18:03'),
  ('Nelsa', 'Nhantumbo', '+258844516258', 'female', 'matola', '2026-11-15 10:18:52'),
  ('Virgínia', 'Chilundo', '+258840753547', 'female', 'ndlavela', '2026-11-15 10:19:32'),
  ('Jordina', 'Muchanga', '+258879427162', 'female', 'munhana', '2026-11-15 10:20:16'),
  ('Justino', 'Cuna', '+258872402042', 'male', 'machava', '2026-11-15 10:21:19'),
  ('António', 'Amone', '+258843693213', 'male', 'ndlavela', '2026-11-15 10:22:12'),
  ('Ângela', 'Matsinhe', '+258853968866', 'female', 'ndlavela', '2026-11-15 10:23:36'),
  ('Nelma', 'Xirinda', '+258872481628', 'female', 'central-c', '2026-11-15 10:24:27'),
  ('Vanessa', 'Mavie', '+258868263989', 'female', '25-de-junho', '2026-11-15 10:25:19'),
  ('Marta', 'Massuanganhe', '+258877744185', 'female', 'zimpeto', '2026-11-15 10:26:04'),
  ('Orlando', 'Mazive', '+258856216363', 'male', 'george-dimitrov', '2026-11-15 10:27:18'),
  ('Amélia', 'Ubisse', '+258847412309', 'female', 'boquisso', '2026-11-15 10:27:50'),
  ('Ana', 'Francisca Tsambe', '+258879235980', 'female', 'marracuene', '2026-11-15 10:29:02'),
  ('Fernando', 'Langa', '+258872708103', 'male', 'marracuene', '2026-11-15 10:29:46'),
  ('Alfredina', 'Chilundo', '+258873074466', 'female', 'ndlavela', '2026-11-15 10:30:26'),
  ('Felizarda', 'Chilundo', '+258878420066', 'female', 'ndlavela', '2026-11-15 10:31:04'),
  ('Sónia', 'Hermenegildo', '+258845377603', 'female', 't-3', '2026-11-15 10:32:12'),
  ('Jaime', 'Ngoca', '+258851972432', 'male', 'george-dimitrov', '2026-11-15 10:33:07'),
  ('Chiara', 'Muchanga', '+258841604785', 'female', 'santa-isabel', '2026-11-15 10:34:42'),
  ('Vanda', 'Muhale', '+258873623330', 'female', 'gaza', '2026-11-15 10:35:38'),
  ('Ivalda', 'Romão', '+258863472539', 'female', 'fajardo', '2026-11-15 10:36:30'),
  ('Poliana', 'Chambule', '+258845856027', 'female', '1o-de-maio', '2026-11-15 10:37:07'),
  ('Sara', 'Massuanganhe', '+258846394007', 'female', 'habel-jafar', '2026-11-15 10:38:16'),
  ('Nairon', 'Miocho', '+258842944912', 'male', 'albazine', '2026-11-15 10:39:12'),
  ('Luzvan', 'Mingo', '+258841337505', 'male', 'intaka-2', '2026-11-15 10:40:15'),
  ('Isabel', 'Nhavango', '+258862509896', 'female', NULL, '2026-11-15 10:57:08'),
  ('Veronica', 'Cabral', '+258847884238', 'female', 'khongolote', '2026-11-15 10:57:41'),
  ('Tissiane', 'Rafael', '+258877041003', 'female', 'bagamoio', '2026-11-15 11:02:04'),
  ('Antonio', 'Jaime', '+258865283623', 'male', 'muhalaze', '2026-11-15 11:02:31'),
  ('Irene', 'Mabote', '+258844182718', 'female', 'albazine', '2026-11-15 11:09:20'),
  ('Alexandre', 'Mingo', '+258847549407', 'male', 'choupal', '2026-11-15 11:10:22'),
  ('Líria', 'Mandlate', '+258840346304', 'female', 'mumemo', '2026-11-15 11:13:27'),
  ('Oryana', 'Sitoe', '+258872504920', 'female', 'malhazine', '2026-11-15 11:14:24'),
  ('Cristovão', 'Lester', '+258840449364', 'male', 'kumbeza', '2026-11-15 11:18:03'),
  ('Clesio', 'Marrime', '+258845170816', 'male', 'khongolote', '2026-11-15 11:19:30'),
  ('Yura', 'Irene', '+258853358082', 'female', 'bagamoio', '2026-11-15 11:21:00'),
  ('Sara', 'Dimande', '+258877168627', 'female', 'george-dimitrov', '2026-11-15 11:22:25'),
  ('Aderito', 'Bié', '+258860565321', 'male', 'khongolote', '2026-11-15 11:23:31'),
  ('Nelson', 'Mingo', '+258844408442', 'male', 'intaka-2', '2026-11-15 11:26:53'),
  ('Vánia', 'Mulendja', '+258840437193', 'female', 'mafalala', '2026-11-15 11:27:54'),
  ('Yune', 'Dacune', '+258833797418', 'male', 'ndlavela', '2026-11-15 11:28:38'),
  ('Esperança', 'Nhavotso', '+258860806815', 'female', 'bagamoio', '2026-11-15 11:30:02'),
  ('Angelina', 'Matavele', '+258863287279', 'female', 'machaquene-c', '2026-11-15 11:31:22'),
  ('Nilsa', 'Manhique', '+258847788562', 'female', 'bagamoio', '2026-11-15 11:33:05'),
  ('Rute', 'Langa', '+258879443684', 'female', 'mateke', '2026-11-15 11:33:36'),
  ('Rute', 'Mapandzene', '+258836602488', 'female', 'sao-damaso', '2026-11-15 11:34:24'),
  ('Jessica', 'Cabral', '+258838191889', 'female', 'magoanine', '2026-11-15 11:35:02'),
  ('Mario', 'Cabral', '+258876418075', 'male', 'magoanine', '2026-11-15 11:35:46'),
  ('Joice', 'Saina', '+258842811140', 'female', 'triunfo', '2026-11-15 11:36:28'),
  ('Dércia', 'Manhique', '+258874209624', 'female', 'malhazine', '2026-11-15 11:38:14'),
  ('Palmira', 'Ernesto', '+258847236430', 'female', 'polana-canico', '2026-11-15 11:39:13'),
  ('Berta', 'Nhantumbo', '+258865055904', 'female', '25-de-junho', '2026-11-15 11:42:30'),
  ('Esmeralda', 'Valoi', '+258845185696', 'female', 'george-dimitrov', '2026-11-15 11:44:18'),
  ('Amelia', 'Machava', '+258867925344', 'female', 'magoanine-a', '2026-11-15 11:48:03'),
  ('Samuel', 'Massango', '+258849668642', 'male', 'khongolote', '2026-11-15 11:49:59'),
  ('Vanilza', 'António', '+258858925594', 'female', 'khongolote', '2026-11-15 11:50:50'),
  ('Jacinta', 'Jafete', '+258863400065', 'female', 'ndlavela', '2026-11-15 11:52:16'),
  ('Sheila', 'Gabriel', '+258850530095', 'female', 'intaka', '2026-11-15 11:53:02'),
  ('David', 'Domingos', '+258843796291', 'male', 'zimpeto', '2026-11-15 11:53:40'),
  ('Altima', 'Domingos', '+258862582064', 'female', 'zimpeto', '2026-11-15 11:54:20'),
  ('Cintia', 'Gota', '+258846609809', 'female', 'bagamoio', '2026-11-15 11:56:17'),
  ('Arsénia', 'Mandlate', '+258846782339', 'female', 'magoanine-cmc', '2026-11-15 11:57:21'),
  ('Promisse', 'Judite', '+258847351570', 'female', 'george-dimitrov', '2026-11-15 11:58:26'),
  ('Ivan', 'José', '+258878224856', 'male', 'bagamoio', '2026-11-15 11:59:19'),
  ('Arminda', 'Palve', '+258879162884', 'female', 'muhalaze', '2026-11-15 12:00:33'),
  ('Carolina', 'Matavele', '+258879194797', 'female', 'hulene-a', '2026-11-15 12:02:09'),
  ('Ana', 'Maria', '+258828564975', 'female', 'bagamoio', '2026-11-15 12:02:42'),
  ('Artur', 'Achimo', '+258877260185', 'male', 'zona-verde', '2026-11-15 12:03:18'),
  ('Fátima', 'Matsinho', '+258879410534', 'female', 'zona-verde', '2026-11-15 12:03:57'),
  ('Edna', 'Banze', '+258862013719', 'female', 'zona-verde', '2026-11-15 12:04:37'),
  ('Domingos', 'Cumbane', '+258866160632', 'male', 'zona-verde', '2026-11-15 12:05:20'),
  ('Jenifa', 'Matola', '+258869628382', 'female', 'zona-verde', '2026-11-15 12:06:26'),
  ('Crimildo', 'Cossa', '+258875123255', 'male', 'khongolote', '2026-11-15 12:06:55'),
  ('Milva', 'Lhamine', '+258875162536', 'female', 'matola-rio', '2026-11-15 12:08:29'),
  ('Wilson', 'Tusse', '+258840240981', 'male', 'zona-verde', '2026-11-15 12:10:19'),
  ('Elina', 'Soto', '+258840681476', 'female', 'george-dimitrov', '2026-11-15 12:11:34'),
  ('Judite', 'Chirombe', '+258846098631', 'female', 'khongolote', '2026-11-15 12:15:04'),
  ('Jania', 'Mucavele', '+258869999067', 'female', 'khongolote', '2026-11-15 12:20:29'),
  ('Stelio', 'Ramine', '+258873857385', 'male', 'mathlemele', '2026-11-15 12:21:31'),
  ('Zulfira', 'Joaquim', '+258879925481', 'female', 'george-dimitrov', '2026-11-15 12:21:44'),
  ('Sara', 'Nhabinde', '+258858480736', 'female', 'khongolote', '2026-11-15 12:22:13'),
  ('Graciete', 'Cavel', '+258872540594', 'female', 'george-dimitrov', '2026-11-15 12:22:25'),
  ('Miguel', 'Angelo', '+258855378477', 'male', 'bagamoio', '2026-11-15 12:22:46'),
  ('Rosita', 'Joaquim', '+258844621494', 'female', 'ndlavela', '2026-11-15 12:23:00'),
  ('Abraão', 'Emidio', '+258842651850', 'male', 'mateke', '2026-11-15 12:23:39'),
  ('Abel', 'Zucula', '+258864642414', 'male', 'khongolote', '2026-11-15 12:24:31'),
  ('Iyarina', 'Chelene', '+258851837169', 'female', 'alto-mae', '2026-11-15 12:24:58'),
  ('Dário', 'Orlando', '+258852490005', 'male', 'albazine', '2026-11-15 12:25:18'),
  ('Eva', 'Simbine', '+258864309696', 'female', 'ndlavela', '2026-11-15 12:25:24'),
  ('Dineu', 'Quive', '+258853098648', 'male', 'magoanine-a', '2026-11-15 12:25:57'),
  ('Edson', 'Nhamayango', '+258842918109', 'male', '25-de-junho', '2026-11-15 12:26:32'),
  ('Ricardina', 'Novela', '+258848935900', 'female', '25-de-junho', '2026-11-15 12:27:05'),
  ('Tassimin', 'Vetina', '+258872947356', 'female', 'matendene', '2026-11-15 12:27:11'),
  ('Agira', 'Namuera', '+258828475070', 'female', 'matendene', '2026-11-15 12:27:59'),
  ('Isaura', 'Macamo', '+258856322802', 'female', 'mahotas', '2026-11-15 12:28:47'),
  ('Amina', 'Chahal', '+258871018389', 'female', 'mahotas', '2026-11-15 12:29:36'),
  ('Helena', 'Marileu', '+258849234821', 'female', 'magoanine-cmc', '2026-11-15 12:30:54'),
  ('Adelaide', 'Dinis', '+258869924221', 'female', 'zona-verde', '2026-11-15 12:32:17'),
  ('Alcinda', 'Chilengue', '+258845877334', 'female', 'mucupe', '2026-11-15 12:33:01'),
  ('Natercia', 'Chilengue', '+258846824580', 'female', 'machava-bedene', '2026-11-15 12:33:56'),
  ('Paulo', 'Machava', '+258840324724', 'male', 'chamanculo-c', '2026-11-15 12:35:00'),
  ('Flara', 'Tomas', '+258861556203', 'female', 'chamanculo', '2026-11-15 12:35:39'),
  ('Leia', 'Domingos', '+258862316216', 'female', 'george-dimitrov', '2026-11-15 12:36:08'),
  ('Julieta', 'Nube', '+258864694008', 'female', 'chamanculo', '2026-11-15 12:36:19'),
  ('Edson', 'Nhancale', '+258844401209', 'male', '25-de-junho', '2026-11-15 12:37:13'),
  ('Neide', 'Nhancale', '+258844928893', 'female', '25-de-junho', '2026-11-15 12:38:17'),
  ('Nekcia', 'de Souza', '+258845657050', 'female', 'xiquelene', '2026-11-15 12:39:41'),
  ('Inácio', 'Francisco', '+258860259601', 'male', 'khongolote', '2026-11-15 12:40:08'),
  ('Feliz', 'Monjane', '+258844945284', 'male', 'muhalaze', '2026-11-15 12:41:00'),
  ('Sónia', 'Alfredo', '+258856710332', 'female', 'george-dimitrov', '2026-11-15 12:41:45'),
  ('Amelia', 'Macamo', '+258863829424', 'female', 'bagamoio', '2026-11-15 12:42:34'),
  ('Ana', 'Francisca da Costa', '+258850376064', 'female', 'alto-mae', '2026-11-15 12:43:43'),
  ('Dulce', 'Mazuze', '+258847996912', 'female', 'intaka', '2026-11-15 12:44:32'),
  ('Tânia', 'Gota', '+258874595120', 'female', '1o-de-maio', '2026-11-15 12:45:33'),
  ('Alfredo', 'Langa', '+258871518763', 'female', 'liberdade', '2026-11-15 12:46:49'),
  ('Celestino', 'Macate', '+258846172479', 'male', 'bobole', '2026-11-15 12:48:30'),
  ('Nelsa', 'Mahumane', '+258823711305', 'female', 'ndlavela', '2026-11-15 12:51:07'),
  ('Fátima', 'Macamo', '+258874181100', 'female', 'jardim', '2026-11-15 12:51:44'),
  ('Mateus', 'Uamusse', '+258845992035', 'male', 'pussulane', '2026-11-15 12:52:34'),
  ('Bernardo', 'Vasconcelos', '+258843765403', 'male', 't-3', '2026-11-15 12:54:41'),
  ('Teila', 'Raju', '+258847764087', 'female', NULL, '2026-11-15 12:56:34'),
  ('Cacilda', 'André', '+258842956666', 'female', 'casa-branca', '2026-11-15 12:56:34'),
  ('Sheila', 'Machava', '+258858828321', 'female', NULL, '2026-11-15 12:57:07'),
  ('Dino', 'Bernardo', '+258863812476', 'male', 'casa-branca', '2026-11-15 12:57:35'),
  ('Dulce', 'Cossa', '+258844570203', 'female', NULL, '2026-11-15 12:57:39'),
  ('Emmanuel', 'Machava', '+258855145271', 'male', NULL, '2026-11-15 12:58:24'),
  ('Amélia', 'Savanguane', '+258853082194', 'female', 'malhazine', '2026-11-15 12:58:48'),
  ('Lino', 'Mateus', '+258875246366', 'male', NULL, '2026-11-15 12:58:53'),
  ('Miquela', 'Neto', '+258867711413', 'female', NULL, '2026-11-15 12:59:24'),
  ('Daria', 'Canaiba', '+258840208703', 'female', NULL, '2026-11-15 13:00:27'),
  ('Sheila', 'de Oliveira', '+258877621799', 'female', NULL, '2026-11-15 13:01:06'),
  ('Amélia', 'Edvalda', '+258841341820', 'female', 'ndlavela', '2026-11-15 13:01:33'),
  ('Selcia', 'Zandamela', '+258849444295', 'female', NULL, '2026-11-15 13:01:52'),
  ('José', 'Chauque', '+258879344295', 'male', NULL, '2026-11-15 13:02:29'),
  ('Emmanuela', 'Matusse', '+258848703056', 'female', 'malhangalene', '2026-11-15 13:04:01'),
  ('Fredyy', 'Ernesto', '+258872595505', 'male', 'george-dimitrov', '2026-11-15 13:06:26'),
  ('Luciano', 'Rungo', '+258879946790', 'male', 'albazine', '2026-11-15 13:09:18'),
  ('Cynthya', 'Mapote', '+258843477961', 'female', 'malhazine', '2026-11-15 13:10:49'),
  ('Luisa', 'Sérgio', '+258865604944', 'female', 'zimpeto', '2026-11-15 13:11:47'),
  ('Agildo', 'Veriato', '+258856143787', 'male', 'ndlavela', '2026-11-15 13:13:06'),
  ('Evanildo', 'Bambamba', '+258845831931', 'male', 'george-dimitrov', '2026-11-15 13:14:27'),
  ('Claudia', 'Albazine', '+258858718228', 'female', 'infulene', '2026-11-15 13:17:01'),
  ('Maria', 'Boa', '+258857364648', 'female', NULL, '2026-11-15 13:18:01'),
  ('Gessica', 'Nhanombe', '+258878102122', 'female', 'matendene', '2026-11-15 13:18:04'),
  ('Lusaia', 'Mucavele', '+258844611540', 'female', NULL, '2026-11-15 13:18:52'),
  ('Francisco', 'Noronha', '+258848420183', 'male', 'khongolote', '2026-11-15 13:19:03'),
  ('Oldina', 'Muale', '+258876950086', 'female', NULL, '2026-11-15 13:19:34'),
  ('Estefânia', 'Dias', '+258847232606', 'female', NULL, '2026-11-15 13:20:08'),
  ('Flora', 'Nhantumbo', '+258845475882', 'female', 'malhazine', '2026-11-15 13:20:12'),
  ('Adelina', 'Massingue', '+258857148418', 'male', 'magoanine-b', '2026-11-15 13:21:12'),
  ('Judite', 'Novela', '+258874448251', 'female', NULL, '2026-11-15 13:21:15'),
  ('Julieta', 'Chicena', '+258877100766', 'female', NULL, '2026-11-15 13:22:22'),
  ('Aílton', 'Justino', '+258847171191', 'male', NULL, '2026-11-15 13:22:52'),
  ('Amélia', 'Arnaldo', '+258878691469', 'female', 'matolagar', '2026-11-15 13:22:55'),
  ('Nalia', 'Jaime', '+258865743340', 'female', NULL, '2026-11-15 13:23:29'),
  ('Alia', 'Bila', '+258847087120', 'female', 't-3', '2026-11-15 13:23:55'),
  ('Eunice', 'Djive', '+258843746533', 'female', NULL, '2026-11-15 13:25:36'),
  ('Momed', 'Mica', '+258879759837', 'male', NULL, '2026-11-15 13:26:30'),
  ('Alcinda', 'Matola', '+258846518721', 'female', NULL, '2026-11-15 13:27:08'),
  ('Rosita', 'Buque', '+258873952655', 'female', 'bagamoio', '2026-11-15 13:27:34'),
  ('Shirley', 'Thay', '+258844394664', 'female', NULL, '2026-11-15 13:27:46'),
  ('Amilton', 'Macuacua', '+258879668665', 'male', 'ndlavela', '2026-11-15 13:28:36'),
  ('Israel', 'Xirindza', '+258849056166', 'male', NULL, '2026-11-15 13:28:46'),
  ('Ebenezer', 'Ramos', '+258841888589', 'female', NULL, '2026-11-15 13:29:24'),
  ('Fernando', 'Carlos', '+258872339038', 'male', NULL, '2026-11-15 13:29:55'),
  ('Madalena', 'Melita', '+258844712328', 'female', NULL, '2026-11-15 13:30:39'),
  ('Eusébio', 'Tchapo', '+258866352747', 'male', 'bagamoio', '2026-11-15 13:30:52'),
  ('Edson', 'Tembe', '+258864743855', 'male', NULL, '2026-11-15 13:31:09'),
  ('Madalena', 'Micas', '+258858313962', 'female', 'magoanine-c', '2026-11-15 13:31:58'),
  ('Kina', 'Azarias', '+258877314807', 'female', 'marracuene', '2026-11-15 13:32:53'),
  ('Joel', 'Dzimbe', '+258866899868', 'male', NULL, '2026-11-15 13:32:56'),
  ('Cristóvão', 'Mungoi', '+258865453868', 'male', NULL, '2026-11-15 13:33:27'),
  ('Rosa', 'Mario', '+258841258627', 'female', 'xipamanine', '2026-11-15 13:33:53'),
  ('Cristina', 'Nhacume', '+258842729453', 'female', NULL, '2026-11-15 13:34:34'),
  ('Cliton', 'Daniel', '+258872246254', 'male', 'alto-mae-a', '2026-11-15 13:35:15'),
  ('Kiluva', 'Adriano', '+258877825938', 'female', NULL, '2026-11-15 13:35:19'),
  ('Orlanda', 'Langa', '+258828754476', 'female', NULL, '2026-11-15 13:35:55'),
  ('Sedieck', 'João', '+258840645985', 'male', 't-3', '2026-11-15 13:36:53'),
  ('Tipswalo', 'Marindza', '+258857510050', 'female', NULL, '2026-11-15 13:37:41'),
  ('Sansão', 'Bila', '+258820633257', 'female', 'liberdade', '2026-11-15 13:37:47'),
  ('Admiro', 'Nhampole', '+258862178076', 'male', NULL, '2026-11-15 13:38:16'),
  ('Sara', 'dos Santos', '+258875065464', 'female', NULL, '2026-11-15 13:38:50'),
  ('Elsa', 'Bila', '+258843947925', 'female', 'liberdade', '2026-11-15 13:39:08'),
  ('Deize', 'Mavila', '+258850161102', 'female', NULL, '2026-11-15 13:39:26'),
  ('Elina', 'Elias', '+258868019692', 'female', 'missao-roque', '2026-11-15 13:40:53'),
  ('Emília', 'Mavile', '+258850277574', 'female', NULL, '2026-11-15 13:42:28'),
  ('Pricila', 'Chilele', '+258878765350', 'female', NULL, '2026-11-15 13:43:26'),
  ('Lídia', 'Cossa', '+258849474482', 'female', NULL, '2026-11-15 13:44:16'),
  ('Naftal', 'Francisco', '+258845892328', 'male', NULL, '2026-11-15 13:45:48'),
  ('Marta', 'Agoa', '+258844845445', 'female', NULL, '2026-11-15 13:46:24'),
  ('Carlos', 'José', '+258866579501', 'male', 'maluana', '2026-11-15 13:47:18'),
  ('Assucena', 'Domingos', '+258847666508', 'female', '25-de-junho', '2026-11-15 13:58:24'),
  ('Loide', 'Miaso', '+258856052252', 'female', '25-de-junho', '2026-11-15 13:59:12'),
  ('Juvencia', 'Raquel', '+258844898760', 'female', 'zona-verde', '2026-11-15 13:59:39'),
  ('Queila', '', '+258879702709', 'female', 'zona-verde', '2026-11-15 14:00:18'),
  ('Tania', 'Joshua', '+258841176265', 'female', 'zona-verde', '2026-11-15 14:01:12'),
  ('Tania', 'Wanela', '+258842989631', 'female', 'alto-mae', '2026-11-15 14:02:03'),
  ('Elisio', 'Joshua', '+258859220642', 'male', NULL, '2026-11-15 14:02:46'),
  ('Selsia', 'Tete', '+258869588380', 'female', 'malhazine', '2026-11-15 14:04:03'),
  ('Marta', 'Cossa', '+258845500080', 'female', 'machava-km15', '2026-11-15 14:04:37'),
  ('Julia', 'Sitoe', '+258857547124', 'female', 'infulene', '2026-11-15 14:05:00'),
  ('Georgina', 'Simbine', '+258868080228', 'female', 'zona-verde', '2026-11-15 14:05:52'),
  ('Gilda', 'Malate', '+258862698234', 'female', 'zona-verde', '2026-11-15 14:09:49'),
  ('Yune', 'Ussilene', '+258860082788', 'female', 'zona-verde', '2026-11-15 14:10:25'),
  ('Orlanda', 'Comé', '+258842271393', 'female', '25-de-junho', '2026-11-15 14:11:08'),
  ('Aurélio', 'Zandamela', '+258858647203', 'male', 'zona-verde', '2026-11-15 14:16:00'),
  ('Evelina', 'Madeira', '+258858882077', 'female', 'gaza', '2026-11-15 14:16:32'),
  ('Keila', 'Singo', '+258848704162', 'female', 'bagamoio', '2026-11-15 14:17:12'),
  ('Luis', 'Antonio', '+258845074229', 'male', 'benfica', '2026-11-15 14:18:43'),
  ('Ana', 'Joice', '+258841222210', 'female', 'alto-mae', '2026-11-15 14:20:28'),
  ('Erquin', 'Cubula', '+258865617757', 'male', 'ndlavela', '2026-11-15 14:21:26'),
  ('Rosimele', '', '+258843476334', 'female', 'zimpeto', '2026-11-15 14:22:38'),
  ('Francisca', 'Macucule', '+258844255917', 'female', 'fomento-cial', '2026-11-15 14:27:19'),
  ('Custódio', 'Emilio', '+258853798426', 'male', 'romao', '2026-11-15 14:29:24'),
  ('Docabel', 'Tualifo', '+258865173010', 'male', 'ndlavela', '2026-11-15 14:35:23'),
  ('Enoque', 'Manuel', '+258865799845', 'male', NULL, '2026-11-15 14:37:51');

-- 3a. Cria só as pessoas cujo número ainda não existe (visitantes)
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at)
SELECT i.name, i.surname, i.gender, i.phone, l.id, 'visitor', NULL, @author_id, i.registered_at, i.registered_at
FROM import_people i
LEFT JOIN locations l ON l.code = i.location_code
LEFT JOIN people p ON p.phone = i.phone
WHERE p.id IS NULL;

-- 3b. Presenças de quem tem número (com a hora em que preencheu o formulário)
INSERT IGNORE INTO person_worship (person_id, worship_id, created_by, created_at, updated_at)
SELECT p.id, @worship_id, @author_id, i.registered_at, i.registered_at
FROM import_people i
JOIN people p ON p.phone = i.phone;

DROP TEMPORARY TABLE import_people;

-- 4. Pessoas sem número (103): cada uma é uma pessoa nova, com a respectiva presença
-- linha 15: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Yolanda', 'Vilanculo', 'female', NULL, (SELECT id FROM locations WHERE code = 'ndlavela'), 'visitor', NULL, @author_id, '2026-11-15 09:24:18', '2026-11-15 09:24:18');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 09:24:18', '2026-11-15 09:24:18');
-- linha 17: Número inválido: 8757481589
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Delfina', 'Artur', 'female', NULL, (SELECT id FROM locations WHERE code = 'boquisso'), 'visitor', NULL, @author_id, '2026-11-15 09:27:47', '2026-11-15 09:27:47');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 09:27:47', '2026-11-15 09:27:47');
-- linha 65: Número inválido: 86671990
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Manuel', 'Sigauque', 'male', NULL, (SELECT id FROM locations WHERE code = 'chamanculo'), 'visitor', NULL, @author_id, '2026-11-15 10:16:15', '2026-11-15 10:16:15');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 10:16:15', '2026-11-15 10:16:15');
-- linha 92: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Yola', 'Matimbe', 'female', NULL, (SELECT id FROM locations WHERE code = 'bagamoio'), 'visitor', NULL, @author_id, '2026-11-15 10:56:32', '2026-11-15 10:56:32');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 10:56:32', '2026-11-15 10:56:32');
-- linha 142: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Nilza', 'Santos', 'female', NULL, (SELECT id FROM locations WHERE code = 'laulane'), 'visitor', NULL, @author_id, '2026-11-15 12:08:49', '2026-11-15 12:08:49');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:08:49', '2026-11-15 12:08:49');
-- linha 143: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Alene', 'Nhamposse', 'female', NULL, (SELECT id FROM locations WHERE code = 'george-dimitrov'), 'visitor', NULL, @author_id, '2026-11-15 12:09:28', '2026-11-15 12:09:28');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:09:28', '2026-11-15 12:09:28');
-- linha 147: Número +258846098631 já pertence a Judite Chirombe (linha 146)
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Hermenegildo', 'Chirombe', 'male', NULL, (SELECT id FROM locations WHERE code = 'khongolote'), 'visitor', NULL, @author_id, '2026-11-15 12:16:47', '2026-11-15 12:16:47');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:16:47', '2026-11-15 12:16:47');
-- linha 177: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Agostinho', '', 'male', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:36:36', '2026-11-15 12:36:36');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:36:36', '2026-11-15 12:36:36');
-- linha 178: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Ana', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:36:48', '2026-11-15 12:36:48');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:36:48', '2026-11-15 12:36:48');
-- linha 179: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Aurélia', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:37:05', '2026-11-15 12:37:05');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:37:05', '2026-11-15 12:37:05');
-- linha 181: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Beatriz', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:37:15', '2026-11-15 12:37:15');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:37:15', '2026-11-15 12:37:15');
-- linha 182: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Rita', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:37:27', '2026-11-15 12:37:27');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:37:27', '2026-11-15 12:37:27');
-- linha 183: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Clementina', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:37:39', '2026-11-15 12:37:39');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:37:39', '2026-11-15 12:37:39');
-- linha 184: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Nocia', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:37:56', '2026-11-15 12:37:56');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:37:56', '2026-11-15 12:37:56');
-- linha 185: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Amélia', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:38:09', '2026-11-15 12:38:09');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:38:09', '2026-11-15 12:38:09');
-- linha 187: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Luneide', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:38:31', '2026-11-15 12:38:31');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:38:31', '2026-11-15 12:38:31');
-- linha 188: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Getro', '', 'male', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:38:47', '2026-11-15 12:38:47');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:38:47', '2026-11-15 12:38:47');
-- linha 189: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Roberto', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:39:07', '2026-11-15 12:39:07');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:39:07', '2026-11-15 12:39:07');
-- linha 190: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Candida', '', 'male', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:39:35', '2026-11-15 12:39:35');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:39:35', '2026-11-15 12:39:35');
-- linha 193: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Celeste', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:40:37', '2026-11-15 12:40:37');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:40:37', '2026-11-15 12:40:37');
-- linha 194: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Ilda', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:40:52', '2026-11-15 12:40:52');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:40:52', '2026-11-15 12:40:52');
-- linha 196: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Teiro', '', 'male', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:41:14', '2026-11-15 12:41:14');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:41:14', '2026-11-15 12:41:14');
-- linha 197: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Gil', '', 'male', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:41:27', '2026-11-15 12:41:27');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:41:27', '2026-11-15 12:41:27');
-- linha 198: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Ana', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:41:45', '2026-11-15 12:41:45');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:41:45', '2026-11-15 12:41:45');
-- linha 200: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Virgínia', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:42:00', '2026-11-15 12:42:00');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:42:00', '2026-11-15 12:42:00');
-- linha 201: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Marilisia', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:42:13', '2026-11-15 12:42:13');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:42:13', '2026-11-15 12:42:13');
-- linha 202: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Graça', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:42:26', '2026-11-15 12:42:26');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:42:26', '2026-11-15 12:42:26');
-- linha 204: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Júlia', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:42:36', '2026-11-15 12:42:36');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:42:36', '2026-11-15 12:42:36');
-- linha 205: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Julia', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:42:46', '2026-11-15 12:42:46');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:42:46', '2026-11-15 12:42:46');
-- linha 206: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Stelia', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:43:08', '2026-11-15 12:43:08');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:43:08', '2026-11-15 12:43:08');
-- linha 207: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Lígia', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:43:19', '2026-11-15 12:43:19');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:43:19', '2026-11-15 12:43:19');
-- linha 208: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Inês', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:43:31', '2026-11-15 12:43:31');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:43:31', '2026-11-15 12:43:31');
-- linha 209: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Marta', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:43:38', '2026-11-15 12:43:38');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:43:38', '2026-11-15 12:43:38');
-- linha 211: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Sandra', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:43:53', '2026-11-15 12:43:53');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:43:53', '2026-11-15 12:43:53');
-- linha 212: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Amélia', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:44:03', '2026-11-15 12:44:03');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:44:03', '2026-11-15 12:44:03');
-- linha 213: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Albertina', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:44:23', '2026-11-15 12:44:23');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:44:23', '2026-11-15 12:44:23');
-- linha 215: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Elsa', 'Armando', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:44:39', '2026-11-15 12:44:39');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:44:39', '2026-11-15 12:44:39');
-- linha 216: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Dercia', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:44:55', '2026-11-15 12:44:55');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:44:55', '2026-11-15 12:44:55');
-- linha 217: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Jasmim', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:45:14', '2026-11-15 12:45:14');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:45:14', '2026-11-15 12:45:14');
-- linha 218: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Zaida', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:45:23', '2026-11-15 12:45:23');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:45:23', '2026-11-15 12:45:23');
-- linha 220: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Zaida', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:45:35', '2026-11-15 12:45:35');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:45:35', '2026-11-15 12:45:35');
-- linha 221: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Lúcia', 'Simbine', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:45:47', '2026-11-15 12:45:47');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:45:47', '2026-11-15 12:45:47');
-- linha 223: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Nelton', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:46:39', '2026-11-15 12:46:39');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:46:39', '2026-11-15 12:46:39');
-- linha 225: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Elias', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:46:51', '2026-11-15 12:46:51');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:46:51', '2026-11-15 12:46:51');
-- linha 226: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Janete', 'Matavel', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:47:10', '2026-11-15 12:47:10');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:47:10', '2026-11-15 12:47:10');
-- linha 227: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Albertina', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:47:25', '2026-11-15 12:47:25');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:47:25', '2026-11-15 12:47:25');
-- linha 228: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Palmira', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:47:37', '2026-11-15 12:47:37');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:47:37', '2026-11-15 12:47:37');
-- linha 229: Número +258840753547 já pertence a Virgínia Chilundo (linha 69)
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Horácio', 'Gil', 'male', NULL, (SELECT id FROM locations WHERE code = 'ndlavela'), 'visitor', NULL, @author_id, '2026-11-15 12:47:48', '2026-11-15 12:47:48');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:47:48', '2026-11-15 12:47:48');
-- linha 230: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Vitoria', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:47:51', '2026-11-15 12:47:51');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:47:51', '2026-11-15 12:47:51');
-- linha 231: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Felicia', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:48:06', '2026-11-15 12:48:06');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:48:06', '2026-11-15 12:48:06');
-- linha 232: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Nezo', 'Mavie', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:48:25', '2026-11-15 12:48:25');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:48:25', '2026-11-15 12:48:25');
-- linha 234: Número inválido: 84499873
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Ana', 'Isildro', 'female', NULL, (SELECT id FROM locations WHERE code = 'ndlavela'), 'visitor', NULL, @author_id, '2026-11-15 12:49:26', '2026-11-15 12:49:26');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:49:26', '2026-11-15 12:49:26');
-- linha 235: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Alice', 'Isildro', 'female', NULL, (SELECT id FROM locations WHERE code = 'ndlavela'), 'visitor', NULL, @author_id, '2026-11-15 12:50:26', '2026-11-15 12:50:26');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:50:26', '2026-11-15 12:50:26');
-- linha 249: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Ancha', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 12:59:36', '2026-11-15 12:59:36');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 12:59:36', '2026-11-15 12:59:36');
-- linha 250: Número inválido: 8727/8103
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Fernando', 'Langa', 'male', NULL, (SELECT id FROM locations WHERE code = 'macaneta'), 'visitor', NULL, @author_id, '2026-11-15 13:00:24', '2026-11-15 13:00:24');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 13:00:24', '2026-11-15 13:00:24');
-- linha 263: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Valter', 'Bila', 'male', NULL, (SELECT id FROM locations WHERE code = 'infulene-d'), 'visitor', NULL, @author_id, '2026-11-15 13:15:15', '2026-11-15 13:15:15');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 13:15:15', '2026-11-15 13:15:15');
-- linha 266: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Laura', 'António', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 13:17:29', '2026-11-15 13:17:29');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 13:17:29', '2026-11-15 13:17:29');
-- linha 274: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Joice', 'Arão', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 13:20:36', '2026-11-15 13:20:36');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 13:20:36', '2026-11-15 13:20:36');
-- linha 283: Número inválido: 8762010136
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Célia', 'Muianga', 'female', NULL, (SELECT id FROM locations WHERE code = 'bagamoio'), 'visitor', NULL, @author_id, '2026-11-15 13:26:28', '2026-11-15 13:26:28');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 13:26:28', '2026-11-15 13:26:28');
-- linha 291: Número +258841337505 já pertence a Luzvan Mingo (linha 91)
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Nilza', 'Massilau', 'female', NULL, (SELECT id FROM locations WHERE code = 'intaka-2'), 'visitor', NULL, @author_id, '2026-11-15 13:29:47', '2026-11-15 13:29:47');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 13:29:47', '2026-11-15 13:29:47');
-- linha 296: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Alcinda', 'Djek', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 13:31:32', '2026-11-15 13:31:32');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 13:31:32', '2026-11-15 13:31:32');
-- linha 297: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Valentina', 'Buque', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 13:31:53', '2026-11-15 13:31:53');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 13:31:53', '2026-11-15 13:31:53');
-- linha 302: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Ricardo', 'Buque', 'male', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 13:33:47', '2026-11-15 13:33:47');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 13:33:47', '2026-11-15 13:33:47');
-- linha 315: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Graciete', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 13:40:13', '2026-11-15 13:40:13');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 13:40:13', '2026-11-15 13:40:13');
-- linha 316: Número inválido: 86800642
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Nicole', 'Lucas', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 13:40:42', '2026-11-15 13:40:42');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 13:40:42', '2026-11-15 13:40:42');
-- linha 321: Número +258877187899 já pertence a Priscila Chilengue (linha 19)
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Stela', 'Chivete', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 13:44:52', '2026-11-15 13:44:52');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 13:44:52', '2026-11-15 13:44:52');
-- linha 322: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Judite', 'Chivete', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 13:45:10', '2026-11-15 13:45:10');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 13:45:10', '2026-11-15 13:45:10');
-- linha 326: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Edvalda', 'Roque', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 13:48:28', '2026-11-15 13:48:28');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 13:48:28', '2026-11-15 13:48:28');
-- linha 327: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Leia', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 13:48:48', '2026-11-15 13:48:48');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 13:48:48', '2026-11-15 13:48:48');
-- linha 331: Número +258820052040 já pertence a Assucênia Lote (linha 21)
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Assucena', 'Laque', 'female', NULL, (SELECT id FROM locations WHERE code = 'somachild'), 'visitor', NULL, @author_id, '2026-11-15 13:56:12', '2026-11-15 13:56:12');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 13:56:12', '2026-11-15 13:56:12');
-- linha 332: Número +258842918109 já pertence a Edson Nhamayango (linha 163)
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Edson', 'Nhamaiare', 'male', NULL, (SELECT id FROM locations WHERE code = 'somachild'), 'visitor', NULL, @author_id, '2026-11-15 13:57:27', '2026-11-15 13:57:27');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 13:57:27', '2026-11-15 13:57:27');
-- linha 337: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Adozinda', 'Machava', 'female', NULL, (SELECT id FROM locations WHERE code = 'zona-verde'), 'visitor', NULL, @author_id, '2026-11-15 14:00:38', '2026-11-15 14:00:38');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:00:38', '2026-11-15 14:00:38');
-- linha 342: Número inválido: 9862592480
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Owana', 'Macamo', 'female', NULL, (SELECT id FROM locations WHERE code = 'zona-verde'), 'visitor', NULL, @author_id, '2026-11-15 14:03:17', '2026-11-15 14:03:17');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:03:17', '2026-11-15 14:03:17');
-- linha 347: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Betuel', 'Siloé', 'male', NULL, (SELECT id FROM locations WHERE code = '1-rotunda'), 'visitor', NULL, @author_id, '2026-11-15 14:08:08', '2026-11-15 14:08:08');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:08:08', '2026-11-15 14:08:08');
-- linha 348: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Natalia', 'Betuel', 'female', NULL, (SELECT id FROM locations WHERE code = '1-rotunda'), 'visitor', NULL, @author_id, '2026-11-15 14:08:28', '2026-11-15 14:08:28');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:08:28', '2026-11-15 14:08:28');
-- linha 353: Número +258862343361 já pertence a Helena Mangana (linha 33)
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Helena', 'Munguambe', 'female', NULL, (SELECT id FROM locations WHERE code = 'bagamoio'), 'visitor', NULL, @author_id, '2026-11-15 14:11:55', '2026-11-15 14:11:55');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:11:55', '2026-11-15 14:11:55');
-- linha 360: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Gilda', 'Antonio', 'female', NULL, (SELECT id FROM locations WHERE code = 'benfica'), 'visitor', NULL, @author_id, '2026-11-15 14:18:06', '2026-11-15 14:18:06');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:18:06', '2026-11-15 14:18:06');
-- linha 362: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Célia', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 14:19:06', '2026-11-15 14:19:06');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:19:06', '2026-11-15 14:19:06');
-- linha 363: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Domingos', 'Macia', 'male', NULL, (SELECT id FROM locations WHERE code = 'ndlavela'), 'visitor', NULL, @author_id, '2026-11-15 14:19:35', '2026-11-15 14:19:35');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:19:35', '2026-11-15 14:19:35');
-- linha 366: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Isabel', '', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 14:21:40', '2026-11-15 14:21:40');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:21:40', '2026-11-15 14:21:40');
-- linha 367: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Cristina', 'Jorge', 'female', NULL, (SELECT id FROM locations WHERE code = 'bobole'), 'visitor', NULL, @author_id, '2026-11-15 14:22:03', '2026-11-15 14:22:03');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:22:03', '2026-11-15 14:22:03');
-- linha 369: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Catia', 'Chirindza', 'female', NULL, (SELECT id FROM locations WHERE code = 'liberdade'), 'visitor', NULL, @author_id, '2026-11-15 14:23:04', '2026-11-15 14:23:04');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:23:04', '2026-11-15 14:23:04');
-- linha 370: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Aida', 'Tsucana', 'female', NULL, (SELECT id FROM locations WHERE code = 'mahotas'), 'visitor', NULL, @author_id, '2026-11-15 14:23:44', '2026-11-15 14:23:44');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:23:44', '2026-11-15 14:23:44');
-- linha 371: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Osvaldo', 'Nhantumbo', 'female', NULL, (SELECT id FROM locations WHERE code = 'romao'), 'visitor', NULL, @author_id, '2026-11-15 14:24:25', '2026-11-15 14:24:25');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:24:25', '2026-11-15 14:24:25');
-- linha 372: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Buna', 'Sambo', 'female', NULL, (SELECT id FROM locations WHERE code = 'romao'), 'visitor', NULL, @author_id, '2026-11-15 14:24:52', '2026-11-15 14:24:52');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:24:52', '2026-11-15 14:24:52');
-- linha 373: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Sandra', 'Matsimbe', 'female', NULL, (SELECT id FROM locations WHERE code = 'george-dimitrov'), 'visitor', NULL, @author_id, '2026-11-15 14:25:36', '2026-11-15 14:25:36');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:25:36', '2026-11-15 14:25:36');
-- linha 374: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Ester', 'Muchanga', 'female', NULL, (SELECT id FROM locations WHERE code = 'magoanine-c'), 'visitor', NULL, @author_id, '2026-11-15 14:25:56', '2026-11-15 14:25:56');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:25:56', '2026-11-15 14:25:56');
-- linha 375: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Francisca', 'Macupi', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 14:26:29', '2026-11-15 14:26:29');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:26:29', '2026-11-15 14:26:29');
-- linha 377: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Adozinda', 'Isabel', 'female', NULL, (SELECT id FROM locations WHERE code = 'zona-verde'), 'visitor', NULL, @author_id, '2026-11-15 14:27:58', '2026-11-15 14:27:58');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:27:58', '2026-11-15 14:27:58');
-- linha 378: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Elvinda', 'Machisso', 'female', NULL, (SELECT id FROM locations WHERE code = 'zona-verde'), 'visitor', NULL, @author_id, '2026-11-15 14:28:37', '2026-11-15 14:28:37');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:28:37', '2026-11-15 14:28:37');
-- linha 380: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Emilio', 'Custodio Junior', 'male', NULL, (SELECT id FROM locations WHERE code = 'pussulane'), 'visitor', NULL, @author_id, '2026-11-15 14:29:57', '2026-11-15 14:29:57');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:29:57', '2026-11-15 14:29:57');
-- linha 381: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Angelo', 'Mabai', 'male', NULL, (SELECT id FROM locations WHERE code = 'alto-mae'), 'visitor', NULL, @author_id, '2026-11-15 14:30:25', '2026-11-15 14:30:25');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:30:25', '2026-11-15 14:30:25');
-- linha 382: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Admiro', 'Vilanculos', 'male', NULL, (SELECT id FROM locations WHERE code = 'tenga'), 'visitor', NULL, @author_id, '2026-11-15 14:31:00', '2026-11-15 14:31:00');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:31:00', '2026-11-15 14:31:00');
-- linha 383: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Dádiva', 'Custodio', 'female', NULL, (SELECT id FROM locations WHERE code = 'ndlavela'), 'visitor', NULL, @author_id, '2026-11-15 14:31:34', '2026-11-15 14:31:34');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:31:34', '2026-11-15 14:31:34');
-- linha 384: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Amelia', '', 'female', NULL, (SELECT id FROM locations WHERE code = 'george-dimitrov'), 'visitor', NULL, @author_id, '2026-11-15 14:32:17', '2026-11-15 14:32:17');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:32:17', '2026-11-15 14:32:17');
-- linha 385: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Salomão', 'Machava', 'male', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 14:32:47', '2026-11-15 14:32:47');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:32:47', '2026-11-15 14:32:47');
-- linha 386: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Arsénio', 'Alexandre', 'male', NULL, (SELECT id FROM locations WHERE code = 'benfica'), 'visitor', NULL, @author_id, '2026-11-15 14:33:12', '2026-11-15 14:33:12');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:33:12', '2026-11-15 14:33:12');
-- linha 387: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Arménio', 'Alexandre', 'male', NULL, (SELECT id FROM locations WHERE code = 'benfica'), 'visitor', NULL, @author_id, '2026-11-15 14:34:08', '2026-11-15 14:34:08');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:34:08', '2026-11-15 14:34:08');
-- linha 388: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Elbachir', 'Camilo', 'male', NULL, (SELECT id FROM locations WHERE code = 'benfica'), 'visitor', NULL, @author_id, '2026-11-15 14:34:27', '2026-11-15 14:34:27');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:34:27', '2026-11-15 14:34:27');
-- linha 390: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Queen', 'Tualifo', 'female', NULL, (SELECT id FROM locations WHERE code = 'ndlavela'), 'visitor', NULL, @author_id, '2026-11-15 14:35:49', '2026-11-15 14:35:49');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:35:49', '2026-11-15 14:35:49');
-- linha 391: Número +258845032260 já pertence a Harazaque Mahumede (linha 58)
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Arazate', 'Mohamed', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 14:36:28', '2026-11-15 14:36:28');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:36:28', '2026-11-15 14:36:28');
-- linha 392: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Meviy', 'Chimisse', 'female', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 14:37:19', '2026-11-15 14:37:19');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:37:19', '2026-11-15 14:37:19');
-- linha 394: Sem número
INSERT INTO people (name, surname, gender, phone, location_id, type, became_member_at, created_by, created_at, updated_at) VALUES ('Deoclercio', 'Francisco', 'male', NULL, NULL, 'visitor', NULL, @author_id, '2026-11-15 14:39:54', '2026-11-15 14:39:54');
INSERT INTO person_worship (person_id, worship_id, created_by, created_at, updated_at) VALUES (LAST_INSERT_ID(), @worship_id, @author_id, '2026-11-15 14:39:54', '2026-11-15 14:39:54');

-- 5. Quem chegou a 5 cultos passa a membro
UPDATE people p
JOIN (SELECT person_id FROM person_worship GROUP BY person_id HAVING COUNT(*) >= 5) c ON c.person_id = p.id
SET p.type = 'member', p.became_member_at = NOW(), p.updated_at = NOW()
WHERE p.type = 'visitor';

COMMIT;

-- Verificação
SELECT @worship_id AS worship_id,
  (SELECT COUNT(*) FROM person_worship WHERE worship_id = @worship_id) AS presentes;
