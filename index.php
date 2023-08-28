<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config/index.php';

use Leaf\Helpers\Authentication;

app()->cors();


// auth()->config("USE_UUID", UUID::v4());

app()->get('/', function () {
	response()->page('./welcome.html');
});

app()->group('/v1', function(){
	
	app()->group('/auth', function(){
		app()->post('/continueWithGoogle', function(){
			$secret = '@_leaf$0Secret!';
			$credentials = request()->get(['email']);
			$user = auth()->login($credentials);
	
			if (!$user) {
				$credentials = request()->get([ 'name', 'username' ,'email', 'avatar']);
				$newUser = auth()->register($credentials, ['email', 'name']);
				response()->exit([
					'status' => 'success',
					'scope' => 'newUser',
					'data' => $newUser
				], 201);
			}
	
			$decodedToken = Authentication::validate($user['token'], $secret);
	
			if (!$decodedToken) {
				$errors = Authentication::errors();
			};
	
			$userProjects = db()
						->select('project', '"id", "name"')
						->where('"ownerId"', $decodedToken->user_id)
						->orderBy('"updated_at"', "desc")
						->limit(20)
						->fetchAll();
				
			$keys = db()
				->select('apikey', '"id", "name"')
				->where('"userId"', $decodedToken->user_id)
				->orderBy('"created_at"', "desc")
				->fetchAll();
	
			response()->json([
				'status' => 'success',
				'scope' => 'existingUser',
				'data' => $user + ['projects' => $userProjects] + ['keys' => $keys]
			]);
		});

		app()->post('/user', function(){
			$user = auth()->user();

			$projects = db()
				->select('project', '"id", "name"')
				->where('"ownerId"', $user['id'])
				->orderBy('"created_at"', "desc")
				->fetchAll();

			$keys = db()
				->select('apikey', '"id", "name"')
				->where('"userId"', $user['id'])
				->orderBy('"created_at"', "desc")
				->fetchAll();

			response()->json(
				[
					"user" => $user + ["projects" => $projects] + ["keys" => $keys]
				]
			);
		});
	});

	app()->group('/keys', function(){
		app()->post('/create', function(){
			$request = request()->get(['name', 'projectId', 'userId']);

			$key = db()
				->insert('apikey')
				->params(
					[
						"name" => $request["name"],
						'"projectId"' => $request["projectId"],
						'"userId"' => $request["userId"]
					]
				)
				->execute();

			$lastId = db()
				->select('apikey', 'id')
				->where('"userId"', $request['userId'])
				->orderBy("created_at", "desc")
				->limit(1)
				->fetchAll()[0]['id'];

			response()->json(
				[
					"message" => "Key created successfully",
					"keyId" => $lastId
				], 201, true
			);
		});
	});

	app()->group('/users', function(){
		app()->get('/', function(){
			$users = db()
				->select('users')
				->fetchAll();

			response()->json($users);
		});

		app()->post('/user', function(){
			$request = request()->get(['id']);

			$user = db()
				->select('users')
				->find($request['id']);


			response()->json($user);
		});
	});

	app()->group('/projects', function(){
		app()->get('/', function(){
			$projects = db()
				->select('project')
				->fetchAll();

			response()->json($projects);
		});

		app()->post('/getProject', function(){
			$request = request()->get(['id']);

			$project = db()
				->select('project')
				->find($request['id']);

			if(!$project) {
				response()->exit(
						["message" => "Project not found"]
				);
			}

			$submissionCount = db()
				->select('submission')
				->where('"projectId"', $request['id'])
				->count();

			$project['fields'] = json_decode($project['fields'], true);

			response()->json( [
				"project" => $project + ["submissionCount" => $submissionCount]
			]);
		});

		app()->post('/getProjectSubmissions', function(){
			$request = request()->get(['id']);

			$project = db()
				->select('project')
				->find($request['id']);

			if(!$project) {
				response()->exit(
						["message" => "Project not found"]
				);
			}

			$submissions = db()
				->select('submission')
				->where('"projectId"', $request['id'])
				->orderBy('"created_at"', "desc")
				->limit(20)
				->fetchAll();

			response()->json(
				[
					"submissions" => $submissions
				]
			);
		});

		app()->post('/create', function(){
			$request = request()->get(['name', 'prefix', 'ownerId']);

			$project = db()
				->insert('project')
				->params(
					[
						"name" => $request['name'],
						"prefix" => $request['prefix'],
						'"ownerId"' => $request['ownerId'],
						"fields" => json_encode([])
					]
				)
				->execute();

			$lastId = db()
				->select('project', 'id')
				->where('"ownerId"', $request['ownerId'])
				->orderBy("created_at", "desc")
				->limit(1)
				->fetchAll()[0]['id'];

			db()
				->insert('apikey')
				->params(
					[
						"name" => $request['name'],
						'"projectId"' => $lastId,
						'"userId"' => $request['ownerId']
					]
				)
				->execute();

			$apiKey = db()
				->select('apikey', 'id')
				->where('"projectId"', $lastId)
				->orderBy("created_at", "DESC")
				->limit(1)
				->fetchAll()[0]['id'];

			response()->json(
				[
					"message" => "Project created successfully",
					"projectId" => $lastId,
					"apiKey" => $apiKey
				], 201, true
			);
		});

		app()->get('/projects', function(){
			$bearer = Leaf\Http\Headers::get("Authorization"); // user id
			$id = substr($bearer, 7);

			$projects = db()
				->select('project', '"id", "name", "prefix", "created_at"')
				->where('"ownerId"', $id)
				->fetchAll();

			response()->json($projects);
		});

		app()->post('updateFields', function(){
			$request = request()->get(['projectId', 'fields']);

			db()
				->update('project')
				->params([
					"fields" => json_encode($request['fields'])
				])
				->where('id', $request['projectId'])
				->execute();
			
			response()->json(
				[
					"message" => "Fields updated successfully"
				], 200, true
			);
		});

		app()->delete('/deleteProject', function(){
			$id = request()->get('id');

			db()
				->delete('project')
				->where('id', $id)
				->execute();

			db()
				->delete('apikey')
				->where('"projectId"', $id)
				->execute();

			db()
				->delete('submission')
				->where('"projectId"', $id)
				->execute();
			
			response()->json(
				[
					"message" => "Project deleted successfully"
				], 200, true
			);
		});
	});

	app()->group('/submissions', function(){
		app()->get('/', function(){
			$projects = db()
				->select('submission')
				->fetchAll();

			response()->json($projects);
		});

		app()->post('/getSubmission', function(){
			$request = request()->get(['id']);

			$submission = db()
				->select('submission')
				->find($request['id']);

			response()->json($submission);
		});

		app()->post('/toggleFavorite', function(){
			$request = request()->get(['submissionId', 'favorite']);

			db()
  				->update("submission")
  				->params(["favorite" => $request['favorite']])
  				->where("id", $request['submissionId'])
  				->execute();

			response()->json(
				"Success",
				200, true
			);
		});

		app()->post('/toggleRead', function(){
			$request = request()->get(['submissionId', 'read']);

			db()
  				->update("submission")
  				->params(["read" => $request['read']])
  				->where("id", $request['submissionId'])
  				->execute();

			response()->json(
				"Success",
				200, true
			);
		});

		app()->delete('/deleteSubmission', function(){
			$id = request()->get('id');
			$submission = db()
				->delete('"submission"')
				->where(
					'id', $id,
					)
				->execute();

			$submissioncheck = db()
					->select('"submission"')
					->find($id);
			
			if ($submissioncheck === false) {
				response()->exit([
					'status' => 'success',
					'data' => 'Submission deleted successfully',
				], 200, true);
			}
			response()->json([
				'status' => 'failed',
				'data' => 'Unable to delete submission',
			], 500, false);
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

			if (!$keyDetails) {
				response()->exit(
					[
						"message" => "Invalid API key"
					], 401
				);
			}

			$fields = db()
				->select('project', 'fields')
				->find($keyDetails["projectId"]);

			$decodedFields = json_decode($fields['fields'], true);

			$required = [];
			$empty = [];

			foreach ($decodedFields as $field) {
				if ($field['required'] && !isset($request[$field["name"]])) {
					// $fieldName = $field["name"];
					$required[] = "Field '" . $field["name"] . "' is required";
					continue;
				}
				if (gettype($request[$field['name']]) != $field['type']) {
					$empty[] = "Field '" . $field["name"] . "' is not of type " . $field['type'];
				}
			}

			if(count($empty) > 0) {
				response()->exit(
					$empty, 400
				);
			};

			if(count($required) > 0) {
				response()->exit(
						$required, 400
				);
			};

			db()
				->insert('submission')
				->params(
					[
						'"projectId"' => $keyDetails["projectId"],
						'"userId"' => $keyDetails["userId"],
						"data" => json_encode($request)
					]
				)
				->execute();
			

			response()->json(
				[
					"message" => "Submission successful"
				], 201, true
			);

		});
	});
});

app()->run();
