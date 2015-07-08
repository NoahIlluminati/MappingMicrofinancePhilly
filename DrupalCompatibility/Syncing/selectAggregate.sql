/*NOT USING THIS*/
CREATE OR REPLACE FUNCTION agg_area_served(locid integer)
RETURNS text AS $all_areas$
  DECLARE
  	all_areas text;
  BEGIN
  EXECUTE 'SELECT array_agg(a.area_name ORDER BY a.area_name)::text
  FROM area_served AS a
  WHERE a.cartodb_id IN (SELECT look_a.area_id FROM lookup_loc_area AS look_a WHERE look_a.loc_id=$1)'
  INTO all_areas
  USING locid;
  RETURN all_areas;
  END;
$all_areas$ LANGUAGE plpgsql;
SELECT agg_area_served(1) FROM untitled_table
