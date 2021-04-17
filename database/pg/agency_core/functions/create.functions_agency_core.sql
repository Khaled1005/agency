/* 
 * Here are a series of be_null functions, to test any data
 * type for NULL or '', and return true for either.
 * FIXME: these could easily be sql functions instead of plpgsql
 */

CREATE OR REPLACE FUNCTION be_null( x int ) RETURNS boolean AS $$

     SELECT $1 IS NULL;

$$ LANGUAGE sql IMMUTABLE;

CREATE OR REPLACE FUNCTION be_null( x smallint ) RETURNS boolean AS $$

     SELECT $1 IS NULL;

$$ LANGUAGE sql IMMUTABLE;

CREATE OR REPLACE FUNCTION be_null( x float ) RETURNS BOOLEAN AS $$

      SELECT $1 IS NULL;

$$ LANGUAGE sql IMMUTABLE;

CREATE OR REPLACE FUNCTION be_null( x interval ) RETURNS BOOLEAN AS $$

      SELECT $1 IS NULL;

$$ LANGUAGE sql IMMUTABLE;

CREATE OR REPLACE FUNCTION be_null( x text ) RETURNS boolean AS $$

     SELECT $1 IS NULL OR $1 = '';

$$ LANGUAGE sql IMMUTABLE;

CREATE OR REPLACE FUNCTION be_null( x date ) RETURNS boolean AS $$

    SELECT $1 IS NULL;

$$ LANGUAGE sql IMMUTABLE;

CREATE OR REPLACE FUNCTION be_null( x boolean ) RETURNS boolean AS $$

    SELECT $1 IS NULL;

$$ LANGUAGE sql IMMUTABLE;

CREATE OR REPLACE FUNCTION be_null( x timestamp ) RETURNS boolean AS $$

    SELECT $1 IS NULL;

$$ LANGUAGE sql IMMUTABLE;

CREATE OR REPLACE FUNCTION be_null( x varchar[] ) RETURNS BOOLEAN AS $$

      SELECT $1 IS NULL;

$$ LANGUAGE sql IMMUTABLE;

CREATE OR REPLACE FUNCTION be_null( x integer[] ) RETURNS BOOLEAN AS $$

      SELECT $1 IS NULL;

$$ LANGUAGE sql IMMUTABLE;

/*
 * These are some convenience functions.
 */

CREATE OR REPLACE FUNCTION null_or( test boolean ) RETURNS boolean AS $$

     SELECT CASE WHEN $1 THEN TRUE ELSE NULL END;

$$ LANGUAGE sql IMMUTABLE;

CREATE OR REPLACE FUNCTION link( url text, label text ) RETURNS text AS $$

     SELECT '<a href="' || $1 || '">' || $2 || '</a>';

$$ language sql IMMUTABLE;


CREATE OR REPLACE FUNCTION bold( input text ) RETURNS TEXT AS $$
BEGIN
     RETURN '<b>' || input || '</b>';
END;$$ LANGUAGE plpgsql IMMUTABLE;     


/*
 * Flipbits are legacy code, for storing less secure (but retrievable) passwords
 */

CREATE OR REPLACE FUNCTION flipbits( string text ) RETURNS text AS $$
DECLARE
     x               integer;
     result          text;
BEGIN
     IF be_null(string) THEN
          RETURN string;
     END IF;

     result := '';
     FOR x in 1..length( string ) LOOP
          result = result || chr(255-ascii(substring(string FROM x FOR 1)) );
     END LOOP;
     RETURN result;
END; $$ LANGUAGE plpgsql IMMUTABLE;

--------------------------------------------
--
--         Generic Procedural Functions
--
--------------------------------------------

CREATE OR REPLACE FUNCTION bool_f(a boolean) RETURNS text AS $$
BEGIN
     RETURN CASE a WHEN TRUE THEN 'Y' WHEN FALSE THEN 'N' ELSE NULL END;
END; $$ LANGUAGE plpgsql IMMUTABLE;

-------------------------------------
--
--     staff functions
--
-------------------------------------

/*
 * Staff assignment functions depend on a main object,
 * and so are version-specific
 */

CREATE OR REPLACE FUNCTION staff_name( sid int4 ) RETURNS text AS $$
DECLARE
     staff_name     text;
BEGIN
     SELECT INTO staff_name TRIM(name_first) || ' ' || TRIM(name_last) FROM staff WHERE staff_id=sid;
     RETURN staff_name;
END; $$ LANGUAGE plpgsql STABLE;

CREATE OR REPLACE FUNCTION staff_name( sid text ) RETURNS text AS $$
DECLARE
     staff_name     text;
BEGIN
     SELECT INTO staff_name TRIM(name_first) || ' ' || TRIM(name_last) FROM staff WHERE staff_id=sid;
     RETURN staff_name;
END; $$ LANGUAGE plpgsql STABLE;

