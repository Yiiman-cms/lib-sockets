<?php
	/**
	 * Created by tokapps TM.
	 * Programmer: gholamreza beheshtian
	 * Mobile:09353466620
	 * Company Phone:05138846411
	 * Site:http://tokapps.ir
	 * Date: 06/03/2019
	 * Time: 06:47 PM
	 */
namespace system\lib\sockets;
use function json_decode;
use Ratchet\ConnectionInterface;
use system\modules\messages\models\Messages;

trait chat{
	public function commandChat(ConnectionInterface $client, $message) {
		$message = json_decode($message, TRUE);
		$chatModel = Chat::findOne($message['chatId']);
		if(!$chatModel) {
			$client->send(json_encode([
				                          'actionType' => 'addMessageResponse',
				                          'status'     => FALSE,
				                          'message'    => 'چت نامعتبر است.',
			                          ]));
			
			return;
		}
		$newMessage = new Messages();
		try {
			$newMessage->chat_id = $chatModel->id;
			$newMessage->message_text = $message['messageText'];
			$newMessage->user_type = $message['userType'];
			$newMessage->created_at = date("Y-m-d Y:i:s", time());
			$result = $newMessage->save();
		} catch(\Exception $exception) {
			$client->send(json_encode([
				                          'actionType' => 'addMessageResponse',
				                          'status'     => FALSE,
				                          'message'    => $exception->getMessage(),
			                          ]));
			$this->addReport($exception);
			return;
		}
		$client->send(json_encode([
			                          'actionType' => 'addMessageResponse',
			                          'result'     => $result,
			                          'message'    => $result == TRUE ? 'پیام شما با موفقیت ارسال شد.' : 'ظاهرا در ارسال پیام مشگلی وجود دارد، لطفا بعدا امتحان کنید.',
		                          ]));
		if($result == TRUE) {
			try {
				foreach($this->clients as $chatClient) {
					$chatClient->send(json_encode([
						                              'actionType' => 'addMessageItem',
						                              'html'       => (new View())->render('@system/modules/user/views/frontend/items/message.php', [
							                              'messageItem'           => $newMessage,
							                              'messageForCurrentUser' => ($chatClient == $client),
						                              ]),
					                              ]));
				}
			} catch(\Exception $exception) {
				$client->send(json_encode([
					                          'actionType' => 'addMessageResponse',
					                          'status'     => FALSE,
					                          'message'    => $exception->getMessage(),
				                          ]));
				return;
			}
		}
	}
}
