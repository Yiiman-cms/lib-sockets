<?php
	/**
	 * Created by tokapps TM.
	 * Programmer: gholamreza beheshtian
	 * Mobile:09353466620
	 * Company Phone:05138846411
	 * Site:http://tokapps.ir
	 * Date: 06/03/2019
	 * Time: 06:50 PM
	 */
	
	namespace system\lib\sockets;
	
	use function json_decode;
	use function json_encode;
	use Ratchet\ConnectionInterface;
	use system\modules\ads\models\Ads;
	use system\modules\ads\models\AdsUserPrice;
	use system\modules\user\models\User;
	
	trait rooms {
		
		
		
		public function commandParticipation( ConnectionInterface $client , $message ) {
			$message  = json_decode( $message , true );
			$this->addReport( $message);
			$fefroosh = Ads::findOne( (int) $message['fefrooshId'] );
			if ( ! $fefroosh ) {
				$client->send(
					json_encode(
						[
							'actionType' => 'addPriceResponse' ,
							'status'     => false ,
							'message'    => 'ففروش پیدا نشد.' ,
						]
					)
				);
				
				return;
			} else if ( $fefroosh->getStartedStatus() == Ads::FEFROOSH_END ) {
				$client->send(
					json_encode(
						[
							'actionType' => 'addPriceResponse' ,
							'status'     => false ,
							'message'    => 'ففروش تمام شده است.' ,
						]
					)
				);
				
				return;
			}
			
			$currentUser = User::findOne( (int) $message['currentUserId'] );
			if ( ! $currentUser ) {
				$client->send(
					json_encode(
						[
							'actionType' => 'addPriceResponse' ,
							'status'     => false ,
							'message'    => 'کاربر یافت نشد.' ,
						]
					)
				);
				
				return;
			}
			
			$priceModel = AdsUserPrice::find()->where(
				[
					'ads_id'  => (int) $fefroosh->id ,
					'user_id' => (int) $currentUser->id ,
				]
			)->one();
			try {
				if ( ! $priceModel || empty( $priceModel ) ) {
					if ( ( (int) $message['price'] ) < $fefroosh->price ) {
						$client->send(
							json_encode(
								[
									'actionType' => 'addPriceResponse' ,
									'status'     => false ,
									'message'    => 'قیمتی که میدهید باید از قیمت پایه بیشتر باشد.' ,
								]
							)
						);
						
						return;
					}
					$priceModel          = new AdsUserPrice();
					$priceModel->ads_id  = (int) $fefroosh->id;
					$priceModel->user_id = (int) $currentUser->id;
					$priceModel->phone   = $currentUser->username;
					$priceModel->price   = (int) $message['price'];
				} else if ( ( (int) $priceModel->price ) >= ( (int) $message['price'] ) ) {
					$client->send(
						json_encode(
							[
								'actionType' => 'addPriceResponse' ,
								'status'     => false ,
								'message'    => 'قیمتی که میدهید باید از قیمت قبلی بیشتر باشد.' ,
							]
						)
					);
					
					return;
				} else {
					$priceModel->price = (int) $message['price'];
				}
			} catch ( \Exception $exception ) {
				$client->send(
					json_encode(
						[
							'actionType' => 'addPriceResponse' ,
							'status'     => false ,
							'message'    => $exception->getMessage() ,
						]
					)
				);
				
				return;
			}
			$result = $priceModel->save();
			$client->send(
				json_encode(
					[
						'actionType' => 'addPriceResponse' ,
						'result'     => $result ,
						'message'    => $result == true ? 'قیمت شما با موفقیت ثبت شد.' : 'ظاهرا در ثبت قیمت مشگلی وجود دارد، لطفا بعدا امتحان کنید.' ,
					]
				)
			);
			if ( $result == true ) {
				foreach ( $this->clients as $chatClient ) {
					$chatClient->send(
						json_encode(
							[
								'actionType'            => 'addPriceItem' ,
								'maxPriceTillNow'       => $fefroosh->getMaxPriceTillNow() ,
								'userId'                => $currentUser->id ,
								'html'                  => '<tr data-user-id="' . $currentUser->id . '"><td><span class="value">' . $priceModel->fullname . '</span></td><td><span class="value">' . number_format(
										$message['price']
									) . '</span></td></tr>' ,
								'htmlForFefrooshActive' => ' <tr data-user-id="' . $currentUser->id . '"><td><span class="value">' . $priceModel->fullname . '</span></td><td><span class="value">' . number_format(
										$message['price']
									) . '</span></td><td><span class="value">' . $priceModel->created_at . '</span></td></tr>' ,
							]
						)
					);
				}
			}
		}
	}
