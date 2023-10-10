<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config/index.php';

use Leaf\Helpers\Authentication;

app()->cors();


// auth()->config("USE_UUID", UUID::v4());
auth()->config("AUTH_NO_PASS", false);
auth()->config("SESSION_ON_REGISTER", false);
auth()->config("HIDE_ID", false);



app()->get('/', function () {
	response()->page('./welcome.html');
});

app()->set404(function () {
	response()->page('./404.html');
  });

app()->group('/v1', function(){
	
	app()->group('/auth', function(){
		app()->post('/continueWithGoogle', function(){
			$secret = '@_leaf$0Secret!';
			$credentials = request()->get(['email']);
			$user = auth()->login($credentials);
	
			if (!$user) {
				$credentials = request()->get([ 'name' ,'email', 'avatar']);
				$newUser = auth()->register([
					'name' => $credentials['name'],
					'email' => $credentials['email'],
					'avatar' => $credentials['avatar'] ?: ""
					// 'password' => $secret
				], ['email']);
				if (!$newUser) {
					response()->exit(auth()->errors());
				}

				$decodedToken = Authentication::validate($newUser['token'], $secret);

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

			$unreadCount = db()
				->select('submission')
				->where([
					'"projectId"' => $request['id'],
					'read' => 'false'
					])
				->count();

			$project['fields'] = json_decode($project['fields'], true);

			response()->json( [
				"project" => $project + ["submissionCount" => $submissionCount] + ["unreadCount" => $unreadCount]
			]);
		});

		app()->post('/getProjectSubmissions', function(){
			$request = request()->get(['id']);
			$page = request()->get('page');

			$projectId = $request['id'];

			$project = db()
				->select('project')
				->find($request['id']);

			if(!$project) {
				response()->exit(
						["message" => "Project not found"]
				);
			}

			$offset = $page * 10;

			$submissionsQuery = db()
				->query("SELECT * FROM submission WHERE \"projectId\" = ? ORDER BY created_at DESC LIMIT 10 OFFSET ?")
				->bind($projectId, $offset)
				->fetchAll();

			// $submissions = db()
			// 	->select('submission')
			// 	->where('"projectId"', $request['id'])
			// 	->orderBy('"created_at"', "desc")
			// 	->limit(10)
			// 	// ->offset($page * 10)
			// 	->fetchAll();

			$hasNextPage = count($submissionsQuery) == 10;

			response()->json(
				[
					"submissions" => $submissionsQuery
				]
			);
		});

		app()->post('/getProjectSubmissionsForDownload', function(){
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
				->fetchAll();

			$submissions = array_map(function($submission){
				return json_decode($submission['data'], true);
			}, $submissions);	



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
				->delete('submission')
				->where('"projectId"', $id)
				->execute();

			db()
				->delete('apikey')
				->where('"projectId"', $id)
				->execute();
				
			db()
				->delete('project')
				->where('id', $id)
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
			$projectId = request()->get('projectId');
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
				$deletedCount = db()
					->select("project", '"deleted_count"')
					->where("id", $projectId)
					->first();

				$newCount = $deletedCount['deleted_count'] + 1;

				db()
					->update("project")
					->params(["deleted_count" => $newCount])
					->where("id", $projectId)
					->execute();


				response()->exit([
					'status' => 'success',
					'data' => 'Submission deleted successfully',
					'deletedCount' => $deletedCount['deleted_count']
				], 200, true);
			}
			response()->json([
				'status' => 'failed',
				'data' => 'Unable to delete submission',
			], 500, false);
		});
	});

	app()->group('/analytics', function(){
		app()->post('/dailySubmissions', function(){
			$request = request()->get(['projectId']);

			$analytics = db()
				->select('submission', 'COUNT(*) as count, DATE_TRUNC(\'day\', "created_at") as day')
				->where('"projectId"', $request['projectId'])
				->where('"created_at"', '>=', date('Y-m-d', strtotime('-7 days')))
				->groupBy('day')
				->orderBy('day', 'desc')
				// ->limit(7)
				->fetchAll();

			response()->json(
				[
					"analytics" => $analytics
				]
			);
		});

		app()->post('/weeklySubmissions', function(){
			$request = request()->get(['projectId']);

			$analytics = db()
				->select('submission', 'COUNT(*) as count, DATE_TRUNC(\'week\', "created_at") as week')
				->where('"projectId"', $request['projectId'])
				->groupBy('week')
				->orderBy('week', 'desc')
				->limit(10)
				->fetchAll();

			response()->json(
				[
					"analytics" => $analytics
				]
			);
		});

		app()->post("/timeOfSubmissions", function(){
			$request = request()->get(['projectId']);

			$analytics = db()
				->select('submission', 'date_part(\'hour\', created_at) AS hour, count(*) AS count')
				->where('"projectId"', $request['projectId'])
				->where('"created_at"', '>=', date('Y-m-d', strtotime('-7 days')))
				->groupBy('hour')
				->orderBy('hour', 'asc')
				->fetchAll();

			response()->json(
				[
					"analytics" => $analytics
				]
			);
		});
	});
});

app()->run();
