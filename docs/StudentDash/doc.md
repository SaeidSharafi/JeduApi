The followign is epxlanation for implementation of Studetn dashbaord.
some of the field are already implmented in eitehr Productables(`Course`,`Seminar`,`DigitalAsset`), `Product` or `ProductDeliveryOption`, some will be stored in the `ProductDeliveryOption->details_json`. and soem need to be added to the models.
read the document, understand the goal, and then start investigating the code base to figure out how to implement this.
soem part of the document require implemnteing endpoitn for handling 3rdparty connections. if the current codebase (services) does not have implmenation DO NOT implemten them, just put TODO and make it wokrs for now.
the only document you are allowed to read in the docs fiel is docs/Dogestions. do not read ther md files, if you feel its needed ask tools to get permission first.

the goal is implmennt student dashboard endpoints 

## My Courses (دوره‌های من)
this area is for user Enrollments wiht DeliveryType of Course and Seminar.
the endpoit is implemented api/v1/shop/my-courses
the follwing is for detail of each item by the type
### Shared data
 - id
 - enrollment_status
 - cover image url
 - access_start_date 
 - access_end_date
 - files (digitalAssets)
   - short_name ?: full_name
   - url
   - filesize
   - type
 - teahcer info [TeacherDetailData]
 - certificate info
   - fields:
     - final grade
     - status ["does not have", "not completed (course)", "in progress","published?"]
     - download url
   - NOTE: Student have to first answer a survey and atfer they compelted the survery they can view teh certificate info. so if a student does not compelted the survery this field will be null
 - survery
   - url
   - message
 - review info from HasReview and ReviewableContract
### Spefice Type Data

#### LIVE_SESSION_BBB and LIVE_SESSION_SKYROOM
- start date
- past session (recorded videos)
    - title
    - date
    - url

#### LMS_MOODLE
- quizes: will be retrieved form Moodle Course quizes and cached by server

#### VIDEO_PLATFORM_SPOTPLAYER
- spot license info:
  ```json
  {
      "_id": "5dcab540796f5d4d48a6570f",
      "key": "00015dcab540796f5d4d48a6570fb7bb74943c36c5e588c0267f9476ff7fe84846070ac971cd311716c6db6a6d603dae09b51395700894cd11c6dd10b71ae24625d1395595eb798844d7d5aec12c",
      "url": "/5e0796ae55fb7a18e83b3554/91d0726373dd525f9d3f57f688299a00/"
  }
  ```

#### IN_PERSON
- location : address , map url (its is stored as a url in db)

---------

## Digital Assets (فایل‌ها و جزوات)
this will be product with DigitalAsset type. 
a simpel array list:
   - product title
   - uuid? or id? of meida: to download using safe controller to download the file (signed, ip  limited,  maybe time limited), will generate again.
    so teh fronted request anoterh api request to a an edpoitn to recive generate url.
check Admin DigitalAssetController for betetr undertanding

------

## Test adn Evaluations (آزمون و ارزیابی)
this will be read from moodle again, the course with quizes that the user is enrolled in moodle.
maybe we create custom enrolment for users?


-----
notes: 
- for survery we will eiterh mark them doen as compelted when user lcick (with time delya) or we use the 3rpd party webhook. we do nto have access to teh webhook documetn for 3rdparty so for now implmente osmething simple
- we will get certificate from the rouyesh service and cache them
- for rouyesh_course_id it can be like ims_course_id, but IMS and Rouyehs are 2 spearate thing

----
Codebase state:
- GET /api/v1/shop/my-courses return EnrollmentData only: uuid,enrollment_status,access_* ,provisioning_data,+product card. No survey/cert/files/teacher/review blocks yet.
- Provisioning system already: enrollments.provisioning_data.providers.{ims,moodle,spotplayer,bbb} written by jobs. requiredProviders() always include ims right now (so “ACTIVE” depends on IMS success).
- No Rouyesh service/job/endpoints. So certificate fetch must be TODO for now.
- Survey/certificate completion storage not exist (no columns/models).
