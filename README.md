# Introduction

The main pupose of this plugin is to import data from a Limesurvey install or to add Limesurvey participants. In addition some work is done on reporting.

It is important to understand that the concept of users in WordPress and Limesurvey is different. In Limesurvey those who take a survey are called participants. Participant data are not stored in the Limesurvey user database. In Limesurvey users are only those who have rights to add or change surveys depending on their user rights.

This plugin can only read data from the Limesurvey database or add Limesurvey partcipants to surveys. It has no functionality to add or change Limesurvey users or add or change surveys.

The prefered way to access Limesurvey data is to link to the Limesurvey database. It is a lot faster and more efficient than using the JSON-RPC interface. If you do not have access to the Limesurvey database, the JSON/RPC interface can be used. This is a relatively slow connection. To improve speed when using JSON/RPC, data are stored in transients and two tables.

# Settings page
## Basic settings

Give the following info:
  + Which surveys are made available on your wordpress website. A comma separated list of survey ids and/or a list of survey group ids. 
  + The url to the the Limesurvey installation.
  + If you want to use the json/rpc interface of Limesurvey.

## Connecting to the Limesurvey database
To prevent locking yourself out from wp-admin due to an error in the Limesurvey database credentials, changing the input fields is not possible when WP_DEBUG is on (WP_DEBUG = true in wp-config.php).

If Limesurvey and WordPress are installed on the same server, you should fill in the database credentials you used installing Limesurvey.

If you want to connect to a database on an other server, the hostname should be the IP-address of the server where the Limesurvey database is installed.  
Probably you have to grant access to this external database by the current domain in the settings of the external database.

## Connecting using the JSON-RPC interface
To be able to use the JSON-RPC interface, the JSON-RPC interface should be switched on in Limesurvey and a Limesurvey user should be defined with the proper rights on the database. Take the following steps:  
Login to your Limesurvey install and navigate to Global settings -> Interfaces and choose JSON-RPC as active interface.
  + Navigate to Manage survey administrators.
  + Create a user with at least all rights on the participant database and on surveys.
  + Add the credentials of this user to the fields below.

You could also use an admin account of course.

### Import survey responses and participants.
Retrieving data with JSON-RPC is slow. To keep loading speeds acceptable data are stored in the WordPress database.

Survey data are stored in transients with a maximum duration of a month. This means you should clear the transients before changes in the survey in Limesurvey are avilable in WordPress. Use the clear transients button below!

Responses and participant data are stored in a database table. These data should be imported manually with the import form on the settings page. When new responses or participamts are added in Limesurvey you have to perform an new import to make them available. Incomplete responses are updated at import.

The link between a wordpress user and a Limesurvey participant is his email address. A participant email address is updated in Limesurvey when the wp-user email address is changed.

### Clear transients
To improve speed the following data are stored in a transient with a maximum duration of a month;

+ The fieldmap of a survey is stored in a transient with the name "fieldmap_{survey_id}". The fieldmap is loaded witth the jSON-RPC functiom "get_fieldmap()". The fieldmap links the question, answer and assesment value to the question code and answer code.
+ The survey properties of a survey are stored in a transient "survey_props_{$survey_id}". The surveyproperties are loaded with the JSON-RPC function "get_survey_properties()"
+ The available surveys are stored in a transient "ls_surveys" as an array of objects with a limeted set of properties.
+ The available survey groups are stored in a transient "ls_survey_groups" as an array of objects.

In case text in Limesurvey has been changed, you have to clear the transients to display the new text.
