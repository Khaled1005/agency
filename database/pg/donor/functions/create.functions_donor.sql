CREATE OR REPLACE FUNCTION address( did int ) RETURNS address AS $$

    SELECT * FROM address WHERE donor_id = $1 AND COALESCE(address_date_end,CURRENT_DATE + 1) > CURRENT_DATE;

$$ LANGUAGE sql STABLE;

CREATE OR REPLACE FUNCTION address_id( int ) RETURNS int AS '
        SELECT address_id  FROM address( $1 );
' LANGUAGE 'sql';

/* Here are a series of be_null functions, to test any data
   type for NULL or '', and return true for either, while
   also not causing PG to barf!
*/




CREATE OR REPLACE FUNCTION be_null( int ) RETURNS boolean AS $$

        SELECT $1 IS NULL;

$$ LANGUAGE sql IMMUTABLE;

CREATE OR REPLACE FUNCTION be_null( text ) RETURNS boolean AS $$

        SELECT $1 IS NULL OR $1 = '';

$$ LANGUAGE sql IMMUTABLE;

CREATE OR REPLACE FUNCTION be_null( date ) RETURNS boolean AS $$

    SELECT $1 IS NULL;

$$ LANGUAGE sql IMMUTABLE;

CREATE OR REPLACE FUNCTION be_null( boolean ) RETURNS boolean AS $$

   SELECT $1 IS NULL;

$$ LANGUAGE sql IMMUTABLE;

CREATE OR REPLACE FUNCTION be_null( timestamp ) RETURNS boolean AS $$

    SELECT $1 IS NULL;

$$ LANGUAGE sql IMMUTABLE;

CREATE OR REPLACE FUNCTION be_null( point ) RETURNS boolean AS $$

    SELECT $1 IS NULL;

$$ LANGUAGE sql IMMUTABLE;

CREATE OR REPLACE FUNCTION flipbits( text ) RETURNS text AS $$
DECLARE
        string          ALIAS FOR $1;
        x                       integer;
        result          text;
BEGIN
        IF string IS NULL OR string = ''
 THEN
                return string;
        END IF;
        result = '';
        FOR x in 1..length( string ) LOOP
                result = result || chr(255-ascii(substring(string FROM x FOR 1)) );
        END LOOP;
        RETURN result;
END; $$ LANGUAGE plpgsql IMMUTABLE;

CREATE OR REPLACE FUNCTION donor_name( int4 ) RETURNS text AS $$

        SELECT donor_name FROM donor WHERE donor_id=$1;

$$ LANGUAGE sql STABLE;

CREATE OR REPLACE FUNCTION staff_name( int4 ) RETURNS text AS $$

        SELECT TRIM(name_first) || ' ' || TRIM(name_last) FROM staff WHERE staff_id = $1;

$$ LANGUAGE sql STABLE;

CREATE OR REPLACE FUNCTION bool_f(boolean) RETURNS text AS $$

        SELECT CASE $1 WHEN true THEN 'Y' WHEN false THEN 'N' ELSE NULL END;

$$ LANGUAGE sql IMMUTABLE;

CREATE OR REPLACE FUNCTION set_preferred_address() RETURNS TRIGGER AS $$
BEGIN
        IF NEW.preferred_address_code IS NULL THEN
                NEW.preferred_address_code := CASE WHEN NEW.donor_type_code = 'INDI' THEN 'HOME'
                                ELSE 'BUSINESS' END;
        END IF;
        RETURN NEW;
END; $$ LANGUAGE plpgsql STABLE;

CREATE TRIGGER set_donor_preferred_address
    BEFORE INSERT ON tbl_donor FOR EACH ROW
    EXECUTE PROCEDURE set_preferred_address();

