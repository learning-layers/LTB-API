<?php
return array(
    'ltbapi\\V2\\Rest\\Tag\\Controller' => array(
        'description' => 'Get or add a tag to a certain stack or other type',
    ),
    'ltbapi\\V2\\Rest\\Stack\\Controller' => array(
        'description' => 'This service manages stacks. The tiles are stored as part of the stack.',
        'collection' => array(
            'GET' => array(
                'description' => 'Search stacks based on name, description, owner, tag (possibly stack_id or stack_code) and the fact whether it may be public or not -public = true will retrieve not only the user\'s stacks but also all the public ones; default it is on. If the parameter \'details\' = true, the tile details will be provided in the collection too.',
                'request' => null,
                'response' => '{
   "_links": {
       "self": {
           "href": "/stack"
       },
       "first": {
           "href": "/stack?page={page}"
       },
       "prev": {
           "href": "/stack?page={page}"
       },
       "next": {
           "href": "/stack?page={page}"
       },
       "last": {
           "href": "/stack?page={page}"
       }
   }
   "_embedded": {
       "stacks": [
           {
               "_links": {
                   "self": {
                       "href": "/stack[/:stack_code]"
                   }
               }
              "name": "The name of the stack",
              "public": "Can the stack be shown to other people than the owner only (default true)",
              "details": "The definition of the stack in the form of a json string",
              "description": "The description of the stack"
           }
       ]
   }
}',
            ),
            'POST' => array(
                'description' => 'Create a new stack, sending the properties: {name, description, [public], details, version}',
                'request' => '{
   "name": "The name of the stack",
   "public": "Can the stack be shown to other people than the owner only (default true)",
   "details": "The definition of the stack in the form of a json string",
   "description": "The description of the stack",
   "version": " A standard version like 1.3.2 or something alike. When nothing passed, the minor will be increased by one"
}',
                'response' => '{
   "_links": {
       "self": {
           "href": "/stack[/:stack_code]"
       }
   }
   "name": "The name of the stack",
   "public": "Can the stack be shown to other people than the owner only (default true)",
   "details": "The definition of the stack in the form of a json string",
   "description": "The description of the stack",
   "version": " A standard version like 1.3.2 or something alike. When nothing passed, the minor will be increased by one"
}',
            ),
            'DELETE' => array(
                'description' => 'Delete a number of stacks based on the parameters name and the id of the current user. At the moment we do not allow this action!',
                'request' => '{
   "name": "The name of the stack"
}',
                'response' => '{
   "_links": {
       "self": {
           "href": "/stack"
       },
       "first": {
           "href": "/stack?page={page}"
       },
       "prev": {
           "href": "/stack?page={page}"
       },
       "next": {
           "href": "/stack?page={page}"
       },
       "last": {
           "href": "/stack?page={page}"
       }
   }
   "_embedded": {
       "stacks": [
           {
               "_links": {
                   "self": {
                       "href": "/stack[/:stack_code]"
                   }
               }
              "name": "The name of the stack",
              "public": "Can the stack be shown to other people than the owner only (default true)",
              "details": "The definition of the stack in the form of a json string",
              "description": "The description of the stack"
           }
       ]
   }
}',
            ),
            'description' => 'A set of stacks',
        ),
        'entity' => array(
            'GET' => array(
                'description' => 'Get a stack based on its id or stack code',
                'request' => null,
                'response' => '{
   "_links": {
       "self": {
           "href": "/stack[/:stack_code]"
       }
   }
   "name": "The name of the stack",
   "public": "Can the stack be shown to other people than the owner only (default true)",
   "details": "The definition of the stack in the form of a json string",
   "description": "The description of the stack"
}',
            ),
            'PATCH' => array(
                'description' => null,
                'request' => null,
                'response' => null,
            ),
            'PUT' => array(
                'description' => null,
                'request' => null,
                'response' => null,
            ),
            'DELETE' => array(
                'description' => null,
                'request' => null,
                'response' => null,
            ),
            'description' => 'A stack: a appsLayout in terms of Social Semantic Server',
        ),
    ),
    'ltbapi\\V2\\Rest\\Favourite\\Controller' => array(
        'collection' => array(
            'GET' => array(
                'description' => 'get all the favourites for a certain user',
                'request' => null,
                'response' => '{
   "_links": {
       "self": {
           "href": "/favourite"
       },
       "first": {
           "href": "/favourite?page={page}"
       },
       "prev": {
           "href": "/favourite?page={page}"
       },
       "next": {
           "href": "/favourite?page={page}"
       },
       "last": {
           "href": "/favourite?page={page}"
       }
   }
   "_embedded": {
       "favourites": [
           {
               "_links": {
                   "self": {
                       "href": "/favourite[/:fav_id]"
                   }
               }
              "fav_id": "",
              "user_id": "",
              "stack_code": "",
              "stack_id": ""
           }
       ]
   }
}',
            ),
            'POST' => array(
                'description' => null,
                'request' => null,
                'response' => null,
            ),
            'description' => 'A list of favourite stacks',
        ),
        'entity' => array(
            'GET' => array(
                'description' => 'Just to see it is there. We are more interested in collections of favourites',
                'request' => null,
                'response' => '{
   "_links": {
       "self": {
           "href": "/favourite[/:fav_id]"
       }
   }

}',
            ),
            'DELETE' => array(
                'description' => 'Get rid of a favourite',
                'response' => '{
   "_links": {
       "self": {
           "href": "/favourite[/:fav_id]"
       }
   }

}',
            ),
            'description' => 'Get one favourite stack',
        ),
        'description' => 'Keep a list of favourite stacks',
    ),
    'ltbapi\\V2\\Rest\\Profile\\Controller' => array(
        'collection' => array(
            'GET' => array(
                'description' => 'Normally we would only request the profile of the current logged in user',
                'request' => null,
                'response' => '{
   "_links": {
       "self": {
           "href": "/profile"
       },
       "first": {
           "href": "/profile?page={page}"
       },
       "prev": {
           "href": "/profile?page={page}"
       },
       "next": {
           "href": "/profile?page={page}"
       },
       "last": {
           "href": "/profile?page={page}"
       }
   }
   "_embedded": {
       "profiles": [
           {
               "_links": {
                   "self": {
                       "href": "/profile[/:profile_id]"
                   }
               }
              "user_id": "The local id to identify the user",
              "user_code": "The code describing the user",
              "name": "The name of the person",
              "surname": "The surname of the person",
              "birthday": "A valid date",
              "prof_nr": "The Profession no",
              "prof_nr_sub": "Not used at the moment. Defaults to 0",
              "partic_nr": "participation number",
              "course_nr": "The number of the course",
              "start_date": "The date of start of apprenticeship",
              "end_date": "Date of completing the apprenticeship",
              "email": "",
              "stack_code": "What stack should be shown by default"
           }
       ]
   }
}',
            ),
            'POST' => array(
                'description' => null,
                'request' => null,
                'response' => null,
            ),
            'description' => 'A list of profiles',
        ),
        'entity' => array(
            'GET' => array(
                'description' => 'get one profile',
                'request' => null,
                'response' => '{
   "_links": {
       "self": {
           "href": "/profile[/:profile_id]"
       }
   }
   "user_id": "The local id to identify the user",
   "user_code": "The code describing the user",
   "name": "The name of the person",
   "surname": "The surname of the person",
   "birthday": "A valid date",
   "prof_nr": "The Profession no",
   "prof_nr_sub": "Not used at the moment. Defaults to 0",
   "partic_nr": "",
   "course_nr": "The number of the course",
   "start_date": "The date of start of apprenticeship",
   "end_date": "Date of completing the apprenticeship",
   "email": "",
   "stack_code": "What stack should be shown by default"
}',
            ),
            'PATCH' => array(
                'description' => 'change a couple of settings in the profile',
                'request' => '{
   "user_id": "The local id to identify the user",
   "user_code": "The code describing the user",
   "name": "The name of the person",
   "surname": "The surname of the person",
   "birthday": "A valid date",
   "prof_nr": "The Profession no",
   "prof_nr_sub": "Not used at the moment. Defaults to 0",
   "partic_nr": "",
   "course_nr": "The number of the course",
   "start_date": "The date of start of apprenticeship",
   "end_date": "Date of completing the apprenticeship",
   "email": "",
   "stack_code": "What stack should be shown by default"
}',
                'response' => null,
            ),
            'PUT' => array(
                'description' => 'Replace your profile completely with a new specification',
                'request' => '{
   "user_id": "The local id to identify the user",
   "user_code": "The code describing the user",
   "name": "The name of the person",
   "surname": "The surname of the person",
   "birthday": "A valid date",
   "prof_nr": "The Profession no",
   "prof_nr_sub": "Not used at the moment. Defaults to 0",
   "partic_nr": "",
   "course_nr": "The number of the course",
   "start_date": "The date of start of apprenticeship",
   "end_date": "Date of completing the apprenticeship",
   "email": "",
   "stack_code": "What stack should be shown by default"
}',
                'response' => null,
            ),
            'DELETE' => array(
                'description' => 'Delete your profile or a specific one (admins only)',
                'request' => '{
   "user_id": "The local id to identify the user",
   "user_code": "The code describing the user",
   "name": "The name of the person",
   "surname": "The surname of the person",
   "birthday": "A valid date",
   "prof_nr": "The Profession no",
   "prof_nr_sub": "Not used at the moment. Defaults to 0",
   "partic_nr": "",
   "course_nr": "The number of the course",
   "start_date": "The date of start of apprenticeship",
   "end_date": "Date of completing the apprenticeship",
   "email": "",
   "stack_code": "What stack should be shown by default"
}',
                'response' => '{
   "_links": {
       "self": {
           "href": "/profile[/:profile_id]"
       }
   }
   "user_id": "The local id to identify the user",
   "user_code": "The code describing the user",
   "name": "The name of the person",
   "surname": "The surname of the person",
   "birthday": "A valid date",
   "prof_nr": "The Profession no",
   "prof_nr_sub": "Not used at the moment. Defaults to 0",
   "partic_nr": "",
   "course_nr": "The number of the course",
   "start_date": "The date of start of apprenticeship",
   "end_date": "Date of completing the apprenticeship",
   "email": "",
   "stack_code": "What stack should be shown by default"
}',
            ),
            'description' => 'Get the profile of the current logged in user (or a specific one if you are admin)',
        ),
        'description' => 'Getting all the profiles based upon a series of parameters (see whitelist). This is only available for admins.',
    ),
    'ltbapi\\V2\\Rest\\SocialSemanticServer\\Controller' => array(
        'description' => 'Gets external SSS entities for example Achso Videos via queries on Social Semantic Server',
        'collection' => array(
            'description' => 'Result of the query on SSS',
            'GET' => array(
                'response' => '{
   "_links": {
       "self": {
           "href": "/sss"
       },
       "first": {
           "href": "/sss?page={page}"
       },
       "prev": {
           "href": "/sss?page={page}"
       },
       "next": {
           "href": "/sss?page={page}"
       },
       "last": {
           "href": "/sss?page={page}"
       }
   }
   "_embedded": {
       "entities": [
           {
               "_links": {
                   "self": {
                       "href": "/sss[/:sss_id]"
                   }
               }

           }
       ]
   }
}',
                'description' => 'Get all entities of a certain type that can be found in SSS. Make sure to pass the type otherwise it takes very long',
            ),
        ),
    ),
    'ltbapi\\V2\\Rpc\\Notify\\Controller' => array(
        'description' => 'Notify current user',
        'POST' => array(
            'description' => 'Allows the current user to notify himself with a reminder of some kind',
            'request' => '{
   "subject": "The subject of the notification",
   "message": "The body of the message",
   "type": "The type of notification. Default is sendmail."
}',
            'response' => '{
   "result": "A boolean indicating success",
   "message": "A possibly empty message indicating the reason of failure or some user message in case of success."
}',
        ),
    ),
    'ltbapi\\V2\\Rpc\\Debug\\Controller' => array(
        'description' => 'Start a debugging session to log messages when a certain bug must be investigated',
        'GET' => array(
            'description' => 'get a session',
            'response' => '{
   "message": "The message of the debug console",
   "value1": "Some value that will be stored",
   "value2": "",
   "value3": "",
   "app": "Indicates whether the console comes from the app or not",
   "version": "The version of the app or the tilestore",
   "verify_code": "To start a debug session, a verify code is necessary"
}',
        ),
        'POST' => array(
            'description' => 'Make a record of a debug statement',
            'request' => '{
   "message": "The message of the debug console",
   "value1": "Some value that will be stored",
   "value2": "Some second value that will be stored",
   "value3": "Some third value that will be stored",
  "debug_code": "a unique id to identify the session"
}',
            'response' => '{
   "message": "The message of the debug console",
   "result": "A boolean indicating success of storing the console message"
  }',
        ),
    ),
);
