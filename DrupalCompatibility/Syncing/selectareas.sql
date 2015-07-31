/*selects all locations and their areas_served as list in string form*/

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
