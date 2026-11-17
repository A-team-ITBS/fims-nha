
INSERT INTO fims.program (program_code, program_name) VALUES
    ('101', 'RESETTLEMENT'),
    ('102', 'HOUSING ASSISTANCE FOR CALAMITY VICTIMS')
ON CONFLICT (program_code) DO NOTHING;

INSERT INTO fims.sub_program (sub_program_code, sub_program_name, program_code) VALUES
    ('101-001', 'FOR ISFs Affected by Infrastructure', '101'),
    ('101-002', 'FOR ISFs Affected by the Supreme Court''s Mandamus to Clean-up the Manila Bay Area', '101'),
    ('102-001', 'Taal Eruption', '102')
ON CONFLICT (sub_program_code) DO NOTHING;

INSERT INTO fims.project (project_code, project_name, program_code, sub_program_code) VALUES
    ('2026-R03-101-001-0001', 'Casa Teodora Phase 1, Brgy. Langkiwa Biñan City, Laguna', '101', '101-001'),
    ('2026-R03-101-001-0002', 'Casa Teodora Phase 2, Brgy. Langkiwa Biñan City, Laguna', '101', '101-001'),
    ('2026-R03-101-001-0003', 'Casa Crisostomo Resettlement Project Phase 2, Brgy. Banlic, Calamba City, Laguna', '101', '101-001'),
    ('2026-R03-101-002-0001', 'Ciudad Kaunlaran Phase 2C, Bacoor Cavite', '101', '101-002'),
    ('2026-R03-101-002-0002', 'Glori-Mor Housing Project 2, Naic, Cavite', '101', '101-002'),
    ('2026-R03-101-002-0003', 'Naic View Residences Phase 2, Brgy. Calubcob, Naic, Cavite', '101', '101-002'),
    ('2026-R03-102-001-0001', 'New Direction Neighbourhood, Brgy. Aya, Talisay, Batangas (Talisay Residences, Brgy. Trance, Talisay, Batangas)', '102', '102-001')
ON CONFLICT (project_code) DO NOTHING;
