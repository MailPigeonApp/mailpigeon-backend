<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config/index.php';

// auth()->config("USE_UUID", UUID::v4());

app()->get('/', function () {
	response()->page('./welcome.html');
});

app()->group('/v1', function(){
	app()->group('/users', function(){
		app()->get('/', function(){
			$users = db()
				->select('users')
				->fetchAll();

			response()->json($users);
		});
	});

	app()->group('/projects', function(){
		app()->get('/', function(){
			$projects = db()
				->select('project')
				->fetchAll();

			response()->json($projects);
		});
	});

	app()->group('/submissions', function(){
		app()->get('/', function(){
			$projects = db()
				->select('submission')
				->fetchAll();

			response()->json($projects);
		});

		app()->post('/submit', function(){
			$request = request()->body();
			$bearer = Leaf\Http\Headers::get("Authorization");
			$key = substr($bearer, 7);
			// $auth = Leaf\Http\Headers::get("Authorization");

			// $key = substr($auth, 6);

			// $decoded = base64_decode($key);
			// list($username,$password) = explode(":",$decoded);

			$keyDetails = db()
				->select('apikey')
				->find($key);

			$fields = db()
				->select('project', 'fields')
				->find($keyDetails["projectId"]);

			// check through the fields and validate the data
			// if the data is valid, save it to the database
			// else, return an error

			$decodedFields = json_decode($fields['fields'], true);

			foreach ($decodedFields as $field) {
				if (!isset($request[$field['name']])) {
					// Return an error message or throw an exception
					echo "Field " . $field['name'] . " is required";
				}
			}
			

			// response()->json(
			// 	[
			// 		// "auth" => $auth,
			// 		// "decoded" => $decoded,
			// 		// "username" => $username,
			// 		// "password" => $password
			// 		"request" => $request,
			// 		"auth key" => $key,
			// 		"key" => $keyDetails,
			// 		"fields" => $fields,
			// 		"decodedFields" => $decodedFields
			// 	]
			// );

		});
	});
});

app()->run();
