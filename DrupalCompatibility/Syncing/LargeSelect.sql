SELECT l.cartodb_id, l.address, l.city, l.email, l.link, l.loc_name,l.mission,l.phone_number,l.state,l.zipcode, t.type, a.area_name
FROM
location AS l
JOIN
lookup_loc_area AS look_a ON l.cartodb_id=look_a.loc_id
JOIN
area_served AS a ON a.cartodb_id=look_a.area_id
JOIN
lookup_loc_type AS look_t ON l.cartodb_id=look_t.loc_id
JOIN
type AS t ON t.cartodb_id=look_t.type_id
ORDER BY l.loc_name
