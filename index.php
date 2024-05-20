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

app()->group('/forms', function() {
	app()->group('/v1', function() {
		app()->group('/auth', function() {
			app()->post('/login', function(){
				$user = auth()->login(request()->get(['email', 'password']));
	
				if (!$user) {
					response()->exit(auth()->errors());
				} else {
					$userProjects = db()
							->select('project', '"id", "name"')
							->where('"ownerId"', $user['user']['id'])
							->orderBy('"updated_at"', "desc")
							->limit(20)
							->fetchAll();
					
					$keys = db()
						->select('apikey', '"id", "name", "projectId"')
						->where('"userId"', $user['user']['id'])
						->orderBy('"created_at"', "desc")
						->fetchAll();

					$forms = db()
						->select('form', '"id", "title", "ref", "created_at", "updated_at"')
						->where('"user_id"', $user['user']['id'])
						->orderBy('"created_at"', "desc")
						->fetchAll();
	
					response()->json([
						'status' => 'success',
						'scope' => 'existingUser',
						'data' => $user + ['keys' => $keys] + ['forms' => $forms]
					]);
				}
			});

			app()->post('/register', function(){
				$request = request()->get(['name', 'email', 'password']);

				$user = auth()->register($request, ['email']);
	
				$errors = auth()->errors();
	
				if ($errors) {
					response()->exit($errors);
				} else {
					$newUser = auth()->login([
						'email' => $request['email'],
						'password' => $request['password']
					]);
	
					response ()->json([
						'status' => 'success',
						'scope' => 'newUser',
						'data' => $newUser + ['projects' => []] + ['keys' => []]
					]);
				}
			});

			app()->post('/continueWithGoogle', function(){
				auth()->config("PASSWORD_VERIFY", false);
				$secret = '@_leaf$0Secret!';
				$credentials = request()->get(['email']);
				$user = auth()->login($credentials);
		
				if (!$user) {
					$registerCredentials = request()->get([ 'name' ,'email', 'avatar']);
					auth()->register([
						'name' => $registerCredentials['name'],
						'email' => $registerCredentials['email'],
						'avatar' => $registerCredentials['avatar'] ?: ""
					], ['email']);
					$newUser = auth()->login($credentials);
					response()->exit([
						'status' => 'success',
						'scope' => 'newUser',
						'data' => $newUser 
					], 201);
	
					// if (!$newUser) {
					// 	response()->exit(auth()->errors());
					// }
	
				}
		
				$decodedToken = Authentication::validate($user['token'], $secret);
		
				if (!$decodedToken) {
					$errors = Authentication::errors();
				};
					
				$keys = db()
					->select('apikey', '"id", "name", "projectId"')
					->where('"userId"', $decodedToken->user_id)
					->orderBy('"created_at"', "desc")
					->fetchAll();

				$forms = db()
					->select('form', '"id", "title", "ref", "created_at", "updated_at"')
					->where('"user_id"', $decodedToken->user_id)
					->orderBy('"created_at"', "desc")
					->fetchAll();
		
				response()->json([
					'status' => 'success',
					'scope' => 'existingUser',
					'data' => $user + ['keys' => $keys] + ['forms' => $forms]
				]);
			});

			app()->post('/checkEmail', function() {
				$email = request()->get('email');
	
				if (!$email) {
					response()->exit([
						'status' => 'failed',
						'exists' => false,
						'meta' => 'Email is required'
					], 400, false);
				}
	
				$user = db()
					->select('users')
					->where('email', $email)
					->first();
	
				if (!$user) {
					response()->exit([
						'status' => 'failed',
						'exits' => false,
						'meta' => 'User not found'
					], 404, false);
				}
	
				if ($user['password'] === null) {
					response()->exit([
						'status' => 'success',
						'exists' => true,
						'meta' => 'Use Google to login'
					], 200, null);
				}
	
				response()->exit([
					'status' => 'success',
					'exists' => true,
					'meta' => $user['name']
				], 200, null);
			});
		});

		app()->group('/user', function() {
			app()->post('/forms', function() {
				$id = request()->get('id');

				$forms = db()
					->select('form', '"id", "title", "created_at", "updated_at"')
					->where('user_id', $id)
					->orderBy('created_at', 'desc')
					->fetchAll();

				response()->json($forms);
			});
		});

		app()->group('/form', function(){
			app()->get('/', function() {
				$forms = db()
					->select('form')
					->fetchAll();
	
				response()->json($forms);
			});

			app()->post('/', function() {
				$id = request()->get('id');

				$form = db()
					->select('form')
					->find($id);

				if (!$form) {
					response()->exit([
						'status' => 'failed',
						'data' => 'Form not found',
					], 500, false);
				}

				$settings = db()
					->select('settings')
					->where('"form_id"', $form['id'])
					->fetchAll();
	
				response()->json([
					"form" => $form + ["settings" => $settings[0]]
				]);
			});

			app()->post('/getForm', function() {
				$request = request()->get(['id']);

				$form = db()
					->select('form')
					->find($request['id']);

				if(!$form) {
					response()->exit(
							["message" => "Form not found"]
					);
				}

				$settings = db()
					->select('settings')
					->where('"form_id"', $form['id'])
					->fetchAll();

				if($form['fields'] == "[]") {
					response()->exit([
						"message" => "Form has no fields"
					]);
				};

				response()->json( [
					"form" => $form + ["settings" => $settings[0]]
				]);
			});

			app()->post('/create', function(){
				$request = request()->get(['title', 'user_id', 'ref'], false);

				$form = db()
					->insert('form')
					->params(
						[
							"title" => $request['title'],
							"user_id" => $request['user_id'],
							"ref" => $request['ref'],
							"fields" => json_encode([])
						]
					)
					->execute();

				$lastId = db()
					->select('form', 'id')
					->where('"user_id"', $request['user_id'])
					->orderBy("created_at", "desc")
					->limit(1)
					->fetchAll()[0]['id'];

				db()
					->insert('settings')
					->params([
						"form_id" => $lastId,
					])
					->execute();

				response()->json(
					[
						"message" => "Form created successfully",
						"formId" => $lastId
					], 201, true
				);


			});

			app()->post('/updateWelcomeScreen', function() {
				$request = request()->get(['formId', 'welcome'], false);

				db()
					->update('form')
					->params([
						"welcome_screen" => json_encode($request['welcome'])
					])
					->where('id', $request['formId'])
					->execute();

				response()->json(
					[
						"message" => "Welcome screen updated successfully"
					], 200, true
				);
			});

			app()->post('/updateThankYouScreen', function() {
				$request = request()->get(['formId', 'thank_you'], false);

				db()
					->update('form')
					->params([
						"thankyou_screen" => json_encode($request['thank_you'])
					])
					->where('id', $request['formId'])
					->execute();

				response()->json(
					[
						"message" => "Thank you screen updated successfully"
					], 200, true
				);
			});

			app()->post('/updateFields', function() {
				$request = request()->get(['formId', 'fields', 'welcome', 'thank_you'], false);
					
				$fields = db()
						->update('form')
						->params([
							"fields" => json_encode($request['fields'])
						])
						->where('id', $request['formId'])
						->execute();

							
				response()->json(
					[
						"message" => "Fields updated successfully"
					], 200, true
				);
			});

			app()->post('/publish', function(){
				$id = request()->get('id');

				db()
					->update('settings')
					->params([
						"is_public" => true
					])
					->where('form_id', $id)
					->execute();

				response()->json(
					[
						"message" => "Form published successfully"
					], 200, true
				);
			});

			app()->delete('/deleteForm', function(){
				$id = request()->get('id');

				db()
					->delete('form')
					->where('id', $id)
					->execute();

				response()->json(
					[
						"message" => "Form deleted successfully"
					], 200, true
				);
			});
		});

		app()->group('/response', function(){
			app()->post('/', function() {
				$id = request()->get('id');

				$formCount= db()
					->select('response', '"increment"')
					->where('form_id', $id)
					->orderBy('created_at', 'desc')
					->limit(1)
					->fetchAll()[0]['increment'] ?? 0;

				$response = db()
						->query('SELECT * FROM response WHERE form_id = ? ORDER BY created_at DESC LIMIT 10 OFFSET ?')
						->bind($id, 0)
						->fetchAll();

				$form = db()
						->select('form', '"fields"')
						->find($id);

				response()->json([
					"responses" => $response,
					"fields" => json_decode($form["fields"]),
					"count" => $formCount
				]);
			});

			app()->post('/submit', function() {
				$request = request()->get(['formId', 'answers']);

				$form = db()
					->select('form', '"id"')
					->find($request['formId']);

				if (!$form) {
					response()->exit([
						'status' => 'failed',
						'data' => 'Form not found',
					], 500, false);
				}

				$responseCount = db()
					->select('response')
					->where('form_id', $request['formId'])
					->count();

				db()
					->insert('response')
					->params(
						[
							"form_id" => $request['formId'],
							"data" => json_encode($request['answers']),
							'increment' => $responseCount + 1
						]
					)
					->execute();

				response()->json(
					[
						"message" => "Response submitted successfully"
					], 201, true
				);
			});

			app()->delete('/', function() {
				$id = request()->get('id');

				db()
					->delete('response')
					->where('id', $id)
					->execute();

				response()->json(
					[
						"message" => "Response deleted successfully"
					], 200, true
				);
			});
		});
	});
});

app()->group('/v1', function(){
	
	app()->group('/auth', function(){
		app()->post('/login', function(){
			$user = auth()->login(request()->get(['email', 'password']));

			if (!$user) {
				response()->exit(auth()->errors());
			} else {
				$userProjects = db()
						->select('project', '"id", "name"')
						->where('"ownerId"', $user['user']['id'])
						->orderBy('"updated_at"', "desc")
						->limit(20)
						->fetchAll();
				
				$keys = db()
					->select('apikey', '"id", "name", "projectId"')
					->where('"userId"', $user['user']['id'])
					->orderBy('"created_at"', "desc")
					->fetchAll();

				response()->json([
					'status' => 'success',
					'scope' => 'existingUser',
					'data' => $user + ['projects' => $userProjects] + ['keys' => $keys]
				]);
			}
		});

		app()->post('/register', function(){
			$request = request()->get(['name', 'email', 'password']);

			$user = auth()->register($request, ['email']);

			$errors = auth()->errors();

			if ($errors) {
				response()->exit($errors);
			} else {
				$newUser = auth()->login([
					'email' => $request['email'],
					'password' => $request['password']
				]);

				response ()->json([
					'status' => 'success',
					'scope' => 'newUser',
					'data' => $newUser + ['projects' => []] + ['keys' => []]
				]);
			}
		});

		app()->post('/continueWithGoogle', function(){
			auth()->config("PASSWORD_VERIFY", false);
			$secret = '@_leaf$0Secret!';
			$credentials = request()->get(['email']);
			$user = auth()->login($credentials);
	
			if (!$user) {
				$registerCredentials = request()->get([ 'name' ,'email', 'avatar']);
				auth()->register([
					'name' => $registerCredentials['name'],
					'email' => $registerCredentials['email'],
					'avatar' => $registerCredentials['avatar'] ?: ""
				], ['email']);
				$newUser = auth()->login($credentials);
				response()->exit([
					'status' => 'success',
					'scope' => 'newUser',
					'data' => $newUser 
				], 201);

				// if (!$newUser) {
				// 	response()->exit(auth()->errors());
				// }

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
				->select('apikey', '"id", "name", "projectId"')
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
				->select('apikey', '"id", "name", "projectId"')
				->where('"userId"', $user['id'])
				->orderBy('"created_at"', "desc")
				->fetchAll();

			response()->json(
				[
					"user" => $user + ["projects" => $projects] + ["keys" => $keys]
				]
			);
		});

		app()->delete("/deleteUser", function(){
			$id = request()->get('id');

			db()
				->delete('submission')
				->where('"userId"', $id)
				->execute();

			db()
				->delete('apikey')
				->where('"userId"', $id)
				->execute();

			db()
				->delete('project')
				->where('"ownerId"', $id)
				->execute();

			db()
				->delete('users')
				->where('id', $id)
				->execute();

			$user = db()->select("users")->find($id);

			if ($user) {
				response()->exit([
					'status' => 'failed',
					'data' => 'Unable to delete user',
				], 500, false);
			}

			response()->json(
				[
					"message" => "User deleted successfully"
				], 200, true
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

		app()->post('/completeWalkthrough', function(){
			$data = request()->get(['has_completed_walkthrough']);
			$user = auth()->update($data);

			if (!$user) {
				$errors = auth()->errors();
				// handle errors
			}
			
			response()->json(
				[
					"message" => "Walkthrough completed successfully"
				], 200, true
			);
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

			$ativatedIntegrations = db()
				->select('project', 'active_integrations')
				->find($request['id']);


			$submissionCount = db()
				->select('submission')
				->where('"projectId"', $request['id'])
				->count();

			// TODO: make the submissions one call and get the count from there 
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

		// TODO: add validation here for field length
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
				->delete('integrations')
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

		app()->post('/getProjectIntegrations', function(){
			$request = request()->get(['id']);

			$integrations = db()
				->select('integrations')
				->where('"projectId"', $request['id'])
				->fetchAll();

			response()->json( [
				"integrations" => $integrations
			]);
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

	app()->group('/integrations', function(){
		app()->get('/', function(){
			$integrations = db()
				->select('integrations')
				->fetchAll();

			response()->json($integrations);
		});

		app()->post('/createIntegration', function(){
			$request = request()->get(['name', 'type', 'projectId', 'userId']);

			// Check if user has turned off the integration

			$disabledCheck = db()
				->select('integrations')
				->where('"projectId"', $request['projectId'])
				->where('"type"', $request['type'])
				->where('"userId"', $request['userId'])
				->fetchAll();

			if ($disabledCheck) {
				// Enable project and return success

				db()
					->query('UPDATE project SET active_integrations = array_append(active_integrations, ?) WHERE id = ?')
					->bind($request['type'], $request['projectId'])
					->execute();

				response()->exit([
					'status' => 'success',
					'message' => 'Integration enabled successfully',
					"integrationId" => $disabledCheck[0]['id']
				], 200, true);
			}

			$integration = db()
				->insert('integrations')
				->params(
					[
						"name" => $request['name'],
						"type" => $request['type'],
						"data" => json_encode([]),
						'"projectId"' => $request['projectId'],
						'"userId"' => $request['userId']
					]
				)
				->execute();
				
			$project = db()
				->select("project", "active_integrations")
				->find($request['projectId']);
				
				if (!$project) {
					response()->exit([
						'status' => 'failed',
						'data' => 'Project not found',
					], 500, false);
				}

			$activeIntegrations = $project['active_integrations'];
			$activeIntegrations = trim($activeIntegrations, "{}");
			$activeIntegrationsArray = explode(",", $activeIntegrations);

			if (in_array($request['type'], $activeIntegrationsArray)) {
				response()->exit([
					'status' => 'failed',
					'data' => ucfirst($request['type']). ' integration already exists',
				], 500, false);
			} else {
				db()
					->query('UPDATE project SET active_integrations = array_append(active_integrations, ?) WHERE id = ?')
					->bind($request['type'], $request['projectId'])
					->execute();
			}

			$lastId = db()
				->select('integrations', 'id')
				->where('"userId"', $request['userId'])
				->orderBy("created_at", "desc")
				->limit(1)
				->fetchAll()[0]['id'];

			response()->json(
				[
					"message" => "Integration created successfully",
					"integrationId" => $lastId
				], 201, true
			);
		});

		app()->post('/registerIntegration', function(){
			$request = request()->get(['id', 'data']);

			$integrationCheck = db()
				->select('integrations')
				->find($request['id']);

			if (!$integrationCheck) {
				response()->exit([
					'status' => 'failed',
					'data' => 'Integration not found',
				], 500, false);
			}

			$integration = db()
				->update('integrations')
				->params(
					[
						"data" => json_encode($request['data'])
					]
				)
				->where('id', $request['id'])
				->execute();

			if (!$integration) {
				response()->exit([
					'status' => 'failed',
					'data' => 'Unable to register integration',
				], 500, false);
			}

			response()->json(
				[
					"message" => "Integration registered successfully"
				], 200, true
			);
		});

		app()->get('/getIntegration', function(){
			$request = request()->get(['id']);

			$integration = db()
				->select('integrations')
				->find($request['id']);

			if(!$integration) {
				response()->exit(
						["message" => "Integration not found"]
				);
			}

			response()->json( [
				"integration" => $integration
			]);
		});

		app()->post('/disableIntegration', function(){
			$request = request()->get(['id', 'projectId', 'type']);

			// Get project's active integrations
			$project = db()
				->select("project", "active_integrations")
				->find($request['projectId']);
				
				if (!$project) {
					response()->exit([
						'status' => 'failed',
						'data' => 'Project not found',
					], 500, false);
				}

			$activeIntegrations = $project['active_integrations'];
			$activeIntegrations = trim($activeIntegrations, "{}");
			$activeIntegrationsArray = explode(",", $activeIntegrations);

			if (($key = array_search($request['type'], $activeIntegrationsArray)) !== false) {
				unset($activeIntegrationsArray[$key]);
			}

			$activeIntegrations = "{" . implode(",", $activeIntegrationsArray) . "}";

			db()
				->update("project")
				->params(["active_integrations" => $activeIntegrations])
				->where("id", $request['projectId'])
				->execute();

			// Check if integration exists on the project
			response()->json(
				[
					"message" => "Integration disabled successfully"
				], 200, true
			);
		});

		app()->delete('/deleteIntegration', function(){
			$id = request()->get('id');
			$projectId = request()->get('projectId');
			$type = request()->get('type');

			$integration = db()
				->delete('"integrations"')
				->where(
					'id', $id,
					)
				->execute();

			$integrationcheck = db()
					->select('"integrations"')
					->find($id);
			
			if ($integrationcheck === false) {
				$project = db()
					->select("project", "active_integrations")
					->find($projectId);
					
					if (!$project) {
						response()->exit([
							'status' => 'failed',
							'data' => 'Project not found',
						], 500, false);
					}

				$activeIntegrations = $project['active_integrations'];
				$activeIntegrations = trim($activeIntegrations, "{}");
				$activeIntegrationsArray = explode(",", $activeIntegrations);

				if (($key = array_search($type, $activeIntegrationsArray)) !== false) {
					unset($activeIntegrationsArray[$key]);
				}

				$activeIntegrations = "{" . implode(",", $activeIntegrationsArray) . "}";

				db()
					->update("project")
					->params(["active_integrations" => $activeIntegrations])
					->where("id", $projectId)
					->execute();

				response()->exit([
					'status' => 'success',
					'data' => 'Integration deleted successfully',
				], 200, true);
			}
		});
	});
});

app()->run();
