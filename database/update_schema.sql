USE petmate;

-- Update users table
ALTER TABLE users 
ADD COLUMN city VARCHAR(100) AFTER address,
ADD COLUMN zip VARCHAR(20) AFTER city,
ADD COLUMN birthdate DATE AFTER zip,
ADD COLUMN employer VARCHAR(100) AFTER birthdate,
ADD COLUMN number_of_pets INT AFTER employer,
ADD COLUMN pet_types TEXT AFTER number_of_pets;

-- Update pets table
ALTER TABLE pets
ADD COLUMN color VARCHAR(50) AFTER breed,
ADD COLUMN sex ENUM('M', 'F') AFTER color,
ADD COLUMN is_neutered BOOLEAN AFTER sex,
ADD COLUMN current_medications TEXT AFTER weight,
ADD COLUMN vaccine_distemper_date DATE AFTER current_medications,
ADD COLUMN vaccine_parvo_date DATE AFTER vaccine_distemper_date,
ADD COLUMN vaccine_rabies_date DATE AFTER vaccine_parvo_date,
ADD COLUMN prior_surgeries TEXT AFTER vaccine_rabies_date,
ADD COLUMN prior_illnesses TEXT AFTER prior_surgeries;

-- Update pet_records table
ALTER TABLE pet_records
ADD COLUMN primary_reason TEXT AFTER visit_date,
ADD COLUMN symptoms TEXT AFTER primary_reason;
