USE denr_xii_portal;

-- Safe to import on an existing installation; names already present are kept.
INSERT IGNORE INTO positions (name) VALUES
('Administrative Aide'),
('Administrative Assistant'),
('Administrative Officer'),
('Accountant'),
('Attorney'),
('Community Affairs Officer'),
('Ecosystems Management Specialist'),
('Engineer'),
('Forester'),
('Forest Ranger'),
('Geodetic Engineer'),
('Information Systems Analyst'),
('Planning Officer'),
('Science Research Specialist'),
('Other');
