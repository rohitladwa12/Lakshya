-- Alter Student Portfolio Table to add start and end dates
ALTER TABLE student_portfolio
ADD COLUMN start_date DATE NULL AFTER sub_title,
ADD COLUMN end_date DATE NULL AFTER start_date;
