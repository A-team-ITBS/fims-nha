CREATE TABLE IF NOT EXISTS fims.program (
    program_code VARCHAR(20) PRIMARY KEY,
    "group" VARCHAR(100),
    program_name VARCHAR(255) NOT NULL
);
