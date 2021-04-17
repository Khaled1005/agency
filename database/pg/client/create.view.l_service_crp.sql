CREATE OR REPLACE VIEW l_service_crp AS
SELECT service_code AS service_crp_code,
description
FROM l_service WHERE is_current AND used_by_crp;
