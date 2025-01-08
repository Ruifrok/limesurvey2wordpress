# Introduction

The main pupose of this plugin is to import data from a Limesurvey install or to add wordpress users to Limesurvey as participants of a given survey. In addition some work is done on reporting.

It is important to understand that the concept of users in WordPress and Limesurvey is different. In Limesurvey those who take a survey are called participants. Participant data are not stored in the Limesurvey user database. In Limesurvey users are only those who have rights to add or edit surveys depending on their user rights.

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

+ The fieldmap of a survey is stored in a transient with the name `fieldmap_{survey_id}`. The fieldmap is loaded witth the jSON-RPC functiom `get_fieldmap()`. The fieldmap links the question, answer and assesment value to the question code and answer code.
+ The survey properties of a survey are stored in a transient `'survey_props_{$survey_id}'`. The surveyproperties are loaded with the JSON-RPC function `get_survey_properties()`.
+ The available surveys are stored in a transient `'ls_surveys'`  as an array of objects with a limeted set of properties.
+ The available survey groups are stored in a transient `'ls_survey_groups'`  as an array of objects.

In case text in Limesurvey has been changed, you have to clear the transients to display the new text.

# Assessment values
Select a survey on the wp-admin page "Assessment values". It will hows the assessment values you enterd in Limesusurvey for the slected survey.  
For two question types, Multiplechoice(question type M) and Yes/No(questiontype Y) it is made possible to add additional assessment values.
There are use cases where you want to differentate the assessment value of different subquestions. For thes questiontypes a form is added where you can fill in the desired assessment value.
For Multiplechoice(question type M) an offset or default value can be added. The default value is the assessment value when none of the subquestions is ticked.

# How to use the plugin
All functions that can be used independent of the way you connect to Limesurvey are located in the file includes/functions.php.

## To get responses
The following function can be used to get reponses from Limesurvey
+ `ls2wp_get_responses_survey($survey_id)`  
  Returns an array of all completed responses of a survey.
+ `ls2wp_get_participant_response($survey_id, $email)`  
  Returns a response in the given $survey_id by email address.
+ `ls2wp_get_response_by_token($survey_id, $token)`  
  Returns a response in the given $surve_id by token.

Each response is an associative array with the following format.  
The first elements contain general info. The submitdate determines if the survey is completed. If it is set it means the survey is completed. If the submitdate is empty the survey is not completed.
The general data are followed by elements with key the question code and value an array of question data.
In the example only the data for one question are shown. All other questions of the survey will be in the actual response in the same format.
```
Array
  (
      [survey_id] => 734171
      [group_survey_id] => 4
      [survey_title] => Survey title
      [datecreated] => 2024-12-20 09:39:14
      [id] => 1
      [token] => sy54GHX0CO3aMbQ
      [submitdate] => 2024-12-20 09:56:26
      [lastpage] => 4
      [startlanguage] => nl
      [seed] => 1573415794
      [startdate] => 2024-12-20 09:54:50
      [datestamp] => 2024-12-20 09:56:26
      [STR01[SQ001]] => Array
          (
              [answer_code] => AO07
              [answer] => 7
              [value] => 4
              [type] => F
              [relevance] => 1
              [title] => STR01
              [aid] => SQ001
              [gid] => 353
              [group_name] => Question group name
              [question] => Question text
              [subquestion] => Subquestion text
          )
```

## Participants
The following functions related to Limesurvey participants are available.  
Wordpress users can be added to a survey participant(token) tabele depending on the setting of the parameter $add_participant.

+ `ls2wp_get_participants($survey_id, $name = '')`  
  Returns an array of subjects with all participants of a survey if only a survey id is given. If the optional $name is used only participants with the given string in firstname or lastname are returned.
+ `ls2wp_get_participant($survey_id, $email, $add_participant = false)`  
  Returns an object with participant properties.  
  If `$add_paticipant` is set to true and no participant is found, the function will add a participant to the Limesurvey participant(token) table of the given survey_id.
+ `ls2wp_get_ls_survey_url($survey_id, $user, $add_participant = true)`  
  Returns a url, that opens the survey for the given wordpress user. The link is identical to the link when a participant is invited from limesurvey by email. This offers the possibility start a survey by loged-in users of your wordpress site.
  If no participant is found for the users email, a participant will be added to the Limesurvey participant(token) table of the given survey_id. If yoy don't want to add a participant set `$add_participant` to `false`.

**When a users email address is changed in Wordpress the email address will be changed in limesurvey.**  
This is done by using the wordpress action hook `'profile_update'`.

The returned partcipant object has the following properties:
```
		stdClass Object
(
    [id] => 123
    [survey_id] => 123456
    [firstname] => firstname
    [lastname] => lastname
    [email] => name@test.nl
    [tid] => 2
    [token] => zQS29QzLQzjIykN
    [language] => nl
    [validfrom] => 2024-12-19 09:54:00
    [validuntil] => 2025-03-31 09:54:00
    [attribute_1] => 
    [attribute_2] => 
    [attribute_3] => 
)
```
### Adding a participant
When a participant is added to a survey participant table by setting $add-participant to true, the following participant data are added:
```
	$participant['firstname'] = $user->first_name;
	$participant['lastname'] = $user->last_name;
	$participant['email'] = $user->user_email;
	$participant['language'] = $survey->language;  
```
When the direct database connection is used in addition a token is generated( a randomly generated 15 character string containg uppercase and lowercase alphanumeric characters).  
When using the JSON-RPC connection the token is generated by Limesurvey.

To add additional participant properties to the Limesurvey participant following filter can be used:
```
apply_filters('ls2wp_add_participant_properties', $participant, $survey_id, $user);```
```

# Reporting
The plugin offers some limeted possibilities for reporting survey results. These should regarded as examples how to use the the data. Depending your specific use case you should add your own functionality.  
There are two filterhooks added to add or remove columns to/from the table.

## Tables

Two tables can be used to view Limesurvey results. The table cab be redered by either echoing the two functions below or by using a shortcode.
### Functions
+ `ls2wp_make_resp_grptable($survey_ids, $group, $email)`
  Presents the response data of a questeion group for one participant. For each question or subquestion the answer is given with the assessment value between brackets.
  In the case of a multiplechoice question the Assessmet value of the main question is given as the sum of the assessment values of the subquestions and the default value(offset).
  $survey_ids: an array of survey_ids or a single survey_id.  
  $group: The group name of a question group.  
  $email: The email address of the participant.  
+ `ls2wp_make_survey_grptable($survey_ids, $group)`
  Presents the the response data of a question group for all responses. For each question or subquestion the mean of the assessment values is presented.
  In case of multiplechoise question the mean of the assessment values of the main question is given and for each subquestion the number times this question is ticked.  
  $survey_ids: an array of syrvey_ids or a single survey_id.  
  $group: The group name of a question group.

### Filter hooks
+ `apply_filters('ls2wp_table_labels', $labels, $response, $group)`
  Can be used to add or remove table labels(columns).
+ `apply_filters('ls2wp_response_table_data', $group_data, $response, $group)`
  Can be used to add or change table data.  

### Shortcodes
These shortcode can be used to ouput the two functions.
+ `[ls2wpresptable surveyids="" groupname="" email=""]`.  
  Renders `ls2wp_make_resp_grptable($survey_ids, $group, $email)`. The attributes are required.
  surveyids: a comma seperated string of survey ids.  
  groupname: the name of the question group.  
  email: the emailadress of the participant
+ `[ls2wpsurveytable surveyids="" groupname=""]`.  
  Renders `ls2wp_make_survey_grptable($survey_ids, $group)`. The attributes are required.
  surveyids: a comma seperated string of survey ids.
  groupname: the name of the question group.   

## Charts
Question results can be presented in a bar chart. The chart gives en frequency distribution of the answers.

+ Using Google charts
+ Using the M-chart plugin

### Google charts
This shortcode can be used to output a googlecharts bar or column chart:
```
[ls2wpgooglecolumnchart surveyids="" questioncode="" direction = "column"]
```
surveyids: a comma seperated string of survey ids.  
questioncode: the questioncode of the displayed question.

**Important. Replace square brackets in question codes by curly braces!!! Wordpress does not accept square brackets in shortcodes.**  

direction: 'column' vertical (default) or 'bar' horizontal.

### M-chart
Install [M-chart](https://wordpress.org/plugins/m-chart/) and activate it.
M chart uses the chart-js library to generate charts.
M chart creates a custom post type to store chart data. Limesurvey2wordpress will create an additional metabox titled "Ls2wp data" in the m chart post with two fields:
+ A field to enter a comma seperated list of survey ids.  
+ A field to enter the question code.

#### How to add a chart
+ Create a new chart.  
+ Give it a suitable name.  
+ Enter some dummy data in the spreadsheet like input area. A chart will be generated below the input area.  
+ Set the chart parameters.  
+ add surveyid(s) and question code in the "Ls2wp data" metabox.

 Add the chart to a page by using the shortcode or by using the m chart block and selecting the desired chart.


