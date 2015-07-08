SELECT *
FROM
(SELECT cartodb_id, city, email, link, loc_name, mission, phone_number, state, zipcode, address,string_agg(area_name,', ' ORDER BY area_name) AS areas
FROM
(SELECT l.cartodb_id, l.address, l.city, l.email, l.link, l.loc_name,l.mission,l.phone_number,l.state,l.zipcode, a.area_name
FROM
location AS l
JOIN
lookup_loc_area AS look_a ON l.cartodb_id=look_a.loc_id
JOIN
area_served AS a ON a.cartodb_id=look_a.area_id
ORDER BY l.loc_name) AS doug
GROUP BY cartodb_id, city, email, link, loc_name, mission, phone_number, state, zipcode, address
ORDER BY cartodb_id)
AS the_areas

JOIN

(SELECT cartodb_id, city, email, link, loc_name, mission, phone_number, state, zipcode, address,string_agg(type,', ' ORDER BY type) AS types
FROM
(SELECT l.cartodb_id, l.address, l.city, l.email, l.link, l.loc_name,l.mission,l.phone_number,l.state,l.zipcode, t.type
FROM
location AS l
JOIN
lookup_loc_type AS look_t ON l.cartodb_id=look_t.loc_id
JOIN
type AS t ON t.cartodb_id=look_t.type_id) AS Fred
GROUP BY cartodb_id, city, email, link, loc_name, mission, phone_number, state, zipcode, address
ORDER BY cartodb_id)
AS the_types
USING (cartodb_id, city, email, link, loc_name, mission, phone_number, state, zipcode, address)
