# MappingMicrofinancePhilly
A map of resources for small businesses in the Greater Philadelphia Area built with Cartodb.js.
The goal of this project is to display information about these resources so that entrepreneurs
can get help form these organizations to start their business.

Internal Documentation contained in the docs folder.
Most docs describe the file that they share a name with.

Check CartoDBStructure.txt for information on the structure of tables in the CartoDB database.
Check IntegratingWithDrupal.txt for how to get this to work on the drupal site.

Directory Structure:
    MappingMicrofinancePhilly
    |
    |   .gitattributes
    |   cancel-circle-white.png
    |   cancel-circle.png
    |   OpenDataPhillySQL.sql
    |   README.txt
    |   todo.txt
    |
    +---docs
    |   |   CartoDBStructure.txt
    |   |   DrupalMap.txt
    |   |   drupalsidebar.txt
    |   |   IntegratingWithDrupal
    |   |
    |   \---Syncing
    |           SqlFiles.txt
    |           updateForm.txt
    |
    \---DrupalCompatibility
        |   DrupalMap.html
        |   drupalsidebar.html
        |
        \---Syncing
                DrupalImport.sql
                selectareas.sql
                selecttypes.sql
                updateForm.php
