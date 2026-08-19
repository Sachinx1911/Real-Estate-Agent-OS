-- ============================================================
-- RE360 — demo seed data (optional). Import AFTER schema.sql.
-- Gives the dashboard realistic content to showcase every panel.
-- Admin USER is created via setup.php (so the password is hashed on the server).
-- ============================================================
SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ---------- Builders ----------
INSERT INTO builders (id, name, company, established_year, office_location, contact_person, designation, mobile, email, website, gst_no,
  total_projects, completed_projects, ongoing_projects, upcoming_projects, delivered_projects, years_in_business, major_locations, reputation_note,
  score_construction, score_delivery, score_location, score_pricing, score_reputation, score_documentation) VALUES
(1,'XYZ Developers','XYZ Group',2005,'New Panvel','Rajesh Khanna','Sales Head','9820011111','sales@xyzdev.in','xyzdevelopers.in','27ABCDE1234F1Z5',24,15,7,2,15,20,'Panvel, Kharghar','Reputed builder, strong delivery record.',8,9,8,7,9,8),
(2,'Green Space Realty','Green Space',2010,'Kharghar','Sunita Rao','GM Sales','9820022222','info@greenspace.in','greenspace.in','27ABCDE1234F1Z6',14,8,5,1,8,15,'Kharghar, Kamothe','Known for green, amenity-rich projects.',8,7,9,7,8,8),
(3,'Today Group','Today Constructions',1998,'Kamothe','Amit Shah','DGM','9820033333','contact@todaygroup.in','todaygroup.in','27ABCDE1234F1Z7',31,22,7,2,22,27,'Kamothe, Kalamboli','Old, trusted name in Navi Mumbai.',7,8,8,8,8,7),
(4,'Galaxy Infra','Galaxy Infra Pvt Ltd',2012,'Panvel','Priya Menon','Sales Manager','9820044444','sales@galaxyinfra.in','galaxyinfra.in','27ABCDE1234F1Z8',9,4,4,1,4,14,'Panvel, Ulwe','Modern towers, premium finishes.',8,7,7,6,7,8),
(5,'Skyline Builders','Skyline',2015,'Ulwe','Vikram Patil','AGM','9820055555','hello@skylinebuilders.in','skylinebuilders.in','27ABCDE1234F1Z9',6,2,3,1,2,11,'Ulwe, Dronagiri','Emerging developer, aggressive pricing.',7,6,8,8,6,7),
(6,'Shree Krupa Developers','Shree Krupa',2008,'Kalamboli','Ganesh Iyer','Sales Head','9820066666','info@shreekrupa.in','shreekrupa.in','27ABCDE1234F1ZA',12,7,4,1,7,18,'Kalamboli, Kamothe','Affordable housing specialist.',7,7,7,9,7,7),
(7,'Metro Lifespaces','Metro Life',2011,'Taloja','Neha Kulkarni','GM','9820077777','sales@metrolife.in','metrolife.in','27ABCDE1234F1ZB',10,5,4,1,5,15,'Taloja, Panvel','Metro-connectivity focused projects.',8,8,8,7,8,8),
(8,'Sunrise Realty','Sunrise',2016,'New Panvel','Rahul Deshmukh','Manager','9820088888','contact@sunriserealty.in','sunriserealty.in','27ABCDE1234F1ZC',5,1,3,1,1,10,'New Panvel','New entrant, RERA-compliant.',7,6,7,8,6,7);

-- ---------- CP details ----------
INSERT INTO cp_details (builder_id, cp_code, commission_pct, commission_basis, payout_stage, payout_timeline, lead_validity_days) VALUES
(1,'XYZ-CP-1042',2.00,'Agreement value','On registration','30 days',30),
(2,'GS-CP-207',2.25,'Agreement value','On agreement','45 days',30),
(3,'TDY-CP-889',2.00,'Agreement value','On registration','30 days',21),
(4,'GLX-CP-311',2.50,'Agreement value','On booking + agreement','30 days',30);

-- ---------- Projects ----------
INSERT INTO projects (id, builder_id, name, type, status, address, node, sector, maharera_no, rera_verified, rera_reg_date, proposed_completion, possession_label,
  total_towers, total_units, land_parcel, project_area, launch_date, price_min, price_max, is_featured, best_for, budget_band, strengths, weaknesses, description) VALUES
(1,1,'Paradise Heights','residential','under_construction','Plot 24, New Panvel (E)','Panvel','12','P52000012345',1,'2024-03-15','2027-12-31','Dec 2027',5,680,'5 Acres','2.75 Acres','2024-01-10',6500000,13500000,1,'First-time buyer, Family','₹75L–95L','Strong railway connectivity\nLarge carpet area\nReputed developer\n10+ lifestyle amenities\n2 mins to Panvel station','Higher floor rise on upper floors\nLimited open parking\nUnder construction (2027)','A landmark residential project with modern architecture, 2 minutes from Panvel Railway Station.'),
(2,2,'Green Valley','residential','under_construction','Sector 18, Kamothe','Kamothe','18','P52000023456',1,'2023-11-20','2027-03-31','Mar 2027',4,420,'3.2 Acres','2.1 Acres','2023-10-05',5900000,10500000,0,'Family, End-user','₹65L–90L','Green, amenity-rich\nGood connectivity\nSpacious layouts','Slightly away from station\nMaintenance on higher side','Amenity-rich green township in the heart of Kamothe.'),
(3,3,'Today''s City','residential','ready','Sector 21, Kalamboli','Kalamboli','21','P52000034567',1,'2021-06-10','2024-06-30','Ready Possession',6,720,'6 Acres','4 Acres','2021-05-01',7000000,11500000,0,'End-user, Investor','₹72L–92L','Ready possession\nTrusted builder\nEstablished neighbourhood','Older design\nResale competition','Ready-to-move homes by a trusted name in Kalamboli.'),
(4,4,'Galaxy Towers','residential','under_construction','Plot 7, Panvel','Panvel','10','P52000045678',1,'2024-01-25','2026-12-31','Dec 2026',3,300,'2.5 Acres','1.8 Acres','2023-12-15',8000000,14500000,0,'Luxury buyer, Family','₹85L–1.2Cr','Premium finishes\nModern towers\nClose to highway','Higher pricing\nLimited units','Premium modern towers with luxury finishes in Panvel.'),
(5,5,'Skyline Residences','residential','new_launch','Sector 19, Ulwe','Ulwe','19','P52000056789',1,'2024-06-01','2028-06-30','Jun 2028',4,360,'3 Acres','2 Acres','2024-05-20',5500000,9500000,0,'Investor, First-time buyer','₹55L–80L','Aggressive launch pricing\nUpcoming Ulwe growth\nMetro-linked','New launch (2028 possession)\nEmerging area','New launch with attractive pricing in fast-growing Ulwe.'),
(6,2,'Green Meadows','residential','under_construction','Sector 34, Kharghar','Kharghar','34','P52000067890',1,'2023-08-15','2026-09-30','Sep 2026',5,500,'4 Acres','2.6 Acres','2023-07-10',7500000,12500000,0,'Family, End-user','₹78L–1.05Cr','Kharghar location\nHills view\nGood schools nearby','Premium pricing','Hillside homes in premium Kharghar.'),
(7,6,'Krupa Residency','residential','ready','Sector 16, Kalamboli','Kalamboli','16','P52000078901',1,'2020-04-10','2023-12-31','Ready Possession',3,240,'1.8 Acres','1.2 Acres','2020-03-01',5000000,7500000,0,'First-time buyer, Investor','₹50L–70L','Affordable\nReady possession\nGood value','Basic amenities','Affordable ready homes in Kalamboli.'),
(8,7,'Metro Enclave','residential','under_construction','Taloja Phase 2','Taloja','2','P52000089012',1,'2023-12-01','2027-06-30','Jun 2027',4,380,'3.1 Acres','2 Acres','2023-11-01',5800000,9200000,0,'Investor, First-time buyer','₹58L–82L','Metro connectivity\nAffordable\nGrowing corridor','Taloja perception','Metro-connected affordable homes in Taloja.'),
(9,1,'XYZ Grandeur','residential','upcoming','New Panvel (W)','Panvel','8','P52000090123',0,NULL,'2028-12-31','Upcoming',6,540,'5.5 Acres','3.2 Acres','2024-08-01',7200000,13000000,0,'Family, Luxury buyer','₹80L–1.1Cr','Reputed builder\nLarge township','Upcoming (pre-launch)','Upcoming grand township by XYZ in New Panvel West.'),
(10,4,'Galaxy Skyline','residential','under_construction','Sector 20, Ulwe','Ulwe','20','P52000101234',1,'2024-02-20','2027-03-31','Mar 2027',3,270,'2.2 Acres','1.5 Acres','2024-02-01',7800000,12000000,0,'Family, Investor','₹80L–1.05Cr','Modern design\nUlwe growth','Emerging area','Modern towers in fast-developing Ulwe.'),
(11,8,'Sunrise Heights','residential','new_launch','New Panvel (E)','Panvel','14','P52000112345',1,'2024-05-10','2028-03-31','Mar 2028',4,340,'2.8 Acres','1.9 Acres','2024-04-15',6200000,9800000,0,'First-time buyer, Investor','₹62L–88L','RERA-compliant\nNew launch offers','New developer','RERA-compliant new launch in New Panvel East.'),
(12,3,'Today Meadows','residential','under_construction','Sector 35, Kamothe','Kamothe','35','P52000123456',1,'2023-09-25','2026-12-31','Dec 2026',5,460,'3.5 Acres','2.3 Acres','2023-09-01',6600000,10800000,0,'Family, End-user','₹68L–95L','Trusted builder\nAmenity-rich','Kamothe density','Amenity-rich family homes in Kamothe.');

-- ---------- Project configurations ----------
INSERT INTO project_configurations (project_id, config, carpet_area, floor_range, facing, unit_count, base_price) VALUES
(1,'1 BHK',450,'1-25','East',180,6500000),
(1,'2 BHK',680,'1-25','East',300,9250000),
(1,'2 BHK',710,'1-25','West',120,9480000),
(1,'3 BHK',950,'5-25','East',80,13500000),
(2,'1 BHK',430,'1-22','North',150,5900000),
(2,'2 BHK',660,'1-22','East',200,8500000),
(2,'3 BHK',900,'3-22','West',70,10500000),
(3,'2 BHK',700,'1-20','East',400,9000000),
(3,'3 BHK',920,'1-20','North',320,11500000),
(4,'2 BHK',720,'1-30','West',180,10500000),
(4,'3 BHK',1050,'5-30','East',120,14500000),
(5,'1 BHK',420,'1-18','East',160,5500000),
(5,'2 BHK',640,'1-18','South',200,8200000);

-- ---------- Towers (Paradise Heights) ----------
INSERT INTO towers (project_id, name, floors, units_per_floor, total_units, lifts, possession) VALUES
(1,'A',25,4,100,3,'Dec 2027'),
(1,'B',25,4,100,3,'Dec 2027'),
(1,'C',22,4,88,2,'Jun 2028'),
(1,'D',20,4,80,2,'Jun 2028'),
(1,'E',25,4,100,3,'Dec 2027');

-- ---------- Inventory (mockup rows + more) ----------
INSERT INTO inventory (project_id, tower, floor, flat_no, config, carpet, facing, status, price, last_verified_at, source, confidence) VALUES
(1,'A',5,'A-501','2 BHK',680,'East','available',9250000, NOW(), 'Sales Manager','high'),
(1,'A',5,'A-502','2 BHK',710,'West','hold',9480000, NOW(), 'Sales Manager','high'),
(1,'A',6,'A-601','2 BHK',680,'East','available',9310000, NOW(), 'Sales Manager','high'),
(1,'A',6,'A-602','2 BHK',710,'West','booked',9540000, DATE_SUB(NOW(), INTERVAL 1 DAY), 'CP Portal','medium'),
(1,'B',7,'B-701','2 BHK',720,'North','available',9720000, NOW(), 'Sales Manager','high'),
(1,'B',7,'B-702','2 BHK',720,'North','token',9720000, NOW(), 'Sales Manager','high'),
(1,'C',12,'C-1201','2 BHK',750,'East','available',10250000, NOW(), 'Sales Manager','high'),
(1,'A',8,'A-801','2 BHK',680,'East','available',9380000, DATE_SUB(NOW(), INTERVAL 4 DAY), 'Sales Manager','medium'),
(1,'A',10,'A-1001','3 BHK',950,'East','available',13500000, DATE_SUB(NOW(), INTERVAL 2 DAY), 'Sales Manager','high'),
(1,'B',3,'B-301','1 BHK',450,'East','available',6500000, DATE_SUB(NOW(), INTERVAL 9 DAY), 'CP Portal','low'),
(1,'E',15,'E-1501','2 BHK',710,'West','available',9800000, NOW(), 'Sales Manager','high'),
(1,'E',16,'E-1601','2 BHK',710,'West','sold',9820000, DATE_SUB(NOW(), INTERVAL 20 DAY), 'CP Portal','medium'),
(2,'A',6,'A-604','2 BHK',660,'East','available',8500000, DATE_SUB(NOW(), INTERVAL 1 DAY), 'Sales Manager','high'),
(2,'B',9,'B-902','3 BHK',900,'West','available',10500000, DATE_SUB(NOW(), INTERVAL 3 DAY), 'Sales Manager','high'),
(2,'A',4,'A-401','1 BHK',430,'North','hold',5900000, DATE_SUB(NOW(), INTERVAL 6 DAY), 'CP Portal','medium'),
(3,'A',8,'A-805','2 BHK',700,'East','available',9000000, DATE_SUB(NOW(), INTERVAL 2 DAY), 'Sales Manager','high'),
(3,'C',11,'C-1104','3 BHK',920,'North','available',11500000, DATE_SUB(NOW(), INTERVAL 5 DAY), 'Sales Manager','medium'),
(4,'A',18,'A-1802','3 BHK',1050,'East','available',14500000, NOW(), 'Sales Manager','high'),
(4,'B',12,'B-1201','2 BHK',720,'West','token',10500000, DATE_SUB(NOW(), INTERVAL 1 DAY), 'Sales Manager','high'),
(5,'A',10,'A-1003','2 BHK',640,'South','available',8200000, NOW(), 'Sales Manager','high'),
(5,'B',5,'B-501','1 BHK',420,'East','available',5500000, DATE_SUB(NOW(), INTERVAL 8 DAY), 'CP Portal','low');

-- ---------- Amenities ----------
INSERT INTO amenities (id, name, category) VALUES
(1,'Swimming Pool','lifestyle'),(2,'Gymnasium','fitness'),(3,'Club House','lifestyle'),(4,'Kids Play Area','kids'),
(5,'Jogging Track','fitness'),(6,'Indoor Games','lifestyle'),(7,'CCTV Surveillance','security'),(8,'Access Control','security'),
(9,'Landscaped Garden','lifestyle'),(10,'Senior Citizen Area','senior'),(11,'Yoga Deck','fitness'),(12,'Party Hall','lifestyle'),
(13,'EV Charging','convenience'),(14,'Amphitheatre','lifestyle'),(15,'Day Care / Creche','kids');

INSERT INTO project_amenities (project_id, amenity_id) VALUES
(1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,9),(1,12),(1,14),
(2,1),(2,2),(2,4),(2,5),(2,9),(2,10),(2,11),
(3,2),(3,3),(3,4),(3,7),(3,9),
(4,1),(4,2),(4,3),(4,12),(4,13),(4,7);

-- ---------- Payment plan + offer (spotlight) ----------
INSERT INTO payment_plans (project_id, plan_name, description, milestones, is_default) VALUES
(1,'Construction Linked (10:10:10:10:60)','Standard CLP payment plan','[{"label":"Booking","pct":10},{"label":"Agreement","pct":10},{"label":"Plinth","pct":10},{"label":"Slabs","pct":10},{"label":"Possession","pct":60}]',1),
(1,'Flexi 20:80','20% now, 80% on possession','[{"label":"On Booking","pct":20},{"label":"On Possession","pct":80}]',0);

INSERT INTO offers (project_id, type, details, official_or_verbal, valid_till, is_active) VALUES
(1,'festive','Ganesh Festival offer — stamp duty waiver on select 2 BHK units','official','2026-09-15',1),
(1,'spot','Spot booking benefit — free covered parking','verbal','2026-08-31',1),
(4,'investor','Investor plan — assured 6% rental for 2 years','official','2026-09-30',1);

-- ---------- Clients + requirements ----------
INSERT INTO clients (id, name, mobile, email, location, profession, purpose, status, source) VALUES
(1,'Ramesh Patel','9876500001','ramesh.p@email.com','Panvel','Business','self','site_visit','Referral'),
(2,'Neha Sharma','9876500002','neha.s@email.com','Kharghar','IT Professional','investment','contacted','Website'),
(3,'Suresh Nair','9876500003','suresh.n@email.com','Kamothe','Doctor','self','negotiation','Walk-in'),
(4,'Anjali Verma','9876500004','anjali.v@email.com','Mumbai','CA','second_home','new','Referral'),
(5,'Kiran Joshi','9876500005','kiran.j@email.com','Panvel','Engineer','self','new','Portal');

INSERT INTO client_requirements (client_id, preferred_location, alt_location, bhk, min_carpet, agreement_budget, all_in_budget, own_contribution, loan_amount, possession_within_months, ready_or_uc) VALUES
(1,'Panvel','Kamothe','2 BHK',650,9000000,10000000,3000000,7000000,24,'any'),
(2,'Kharghar','Panvel','2 BHK',600,8500000,9500000,4000000,5500000,36,'under_construction'),
(3,'Kamothe','Kalamboli','3 BHK',850,10500000,11500000,4500000,7000000,18,'ready'),
(4,'Panvel','Ulwe','2 BHK',650,8000000,9000000,9000000,0,36,'any'),
(5,'Panvel','','2 BHK',680,9000000,10000000,3000000,7000000,30,'any');

-- ---------- Bookings (this month) ----------
INSERT INTO bookings (client_id, project_id, flat_id, value, stage, booking_date) VALUES
(1,1,4,9540000,'booked',CURDATE()),
(3,3,NULL,11500000,'agreement',DATE_SUB(CURDATE(), INTERVAL 5 DAY)),
(2,4,19,10500000,'token',DATE_SUB(CURDATE(), INTERVAL 2 DAY));

-- ---------- Tasks / follow-ups (mockup) ----------
INSERT INTO tasks (title, type, related_type, related_id, subtitle, due_at, priority, status) VALUES
('Follow up with Ramesh Patel','followup','client',1,'Paradise Heights – Site Visit', CONCAT(CURDATE(),' 15:00:00'),'high','open'),
('Document collection – Green Valley','document','project',2,'Agreement documents pending', CONCAT(DATE_ADD(CURDATE(), INTERVAL 1 DAY),' 11:00:00'),'medium','open'),
('Call back – Neha Sharma','callback','client',2,'Budget discussion', CONCAT(DATE_ADD(CURDATE(), INTERVAL 2 DAY),' 10:00:00'),'low','open');

-- ---------- Activity log (Recent Updates feed) ----------
INSERT INTO activity_log (action, entity_type, entity_id, message, icon, created_at) VALUES
('inventory_updated','project',2,'Green Valley inventory updated','inventory', NOW()),
('offer_added','project',1,'New offer added – Paradise Heights','tag', DATE_SUB(NOW(), INTERVAL 75 MINUTE)),
('payment_updated','project',4,'Payment plan updated – Galaxy Towers','money', DATE_SUB(NOW(), INTERVAL 1 DAY)),
('inventory_verified','project',3,'Inventory verified – Today''s City','verified', DATE_SUB(NOW(), INTERVAL 1 DAY)),
('project_added','project',5,'New project added – Skyline Residences','building', DATE_SUB(NOW(), INTERVAL 1 DAY));

SET FOREIGN_KEY_CHECKS = 1;
