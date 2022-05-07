<?php

class AnadoluAjansi
{
	private $base_url = "https://api.aa.com.tr/abone";
	private $username = "" // username;
	private $password = "" // password;
	
  // Newsml 2.9 to array
	private function newsmlToArray($xml)
	{
		$xml = simplexml_load_string($xml);
		
		$news            = [];
		$news['code']    = (string) ($xml->itemSet->newsItem['guid']);
		$news['title']   = (string) $xml->itemSet->newsItem->contentMeta->headline;
		$news['summary'] = (string) $xml->itemSet->newsItem->contentSet->inlineXML->nitf->body->{'body.head'}->abstract;
		$news['content'] = (string) $xml->itemSet->newsItem->contentSet->inlineXML->nitf->body->{'body.content'};
		
		foreach($xml->itemSet->newsItem->itemMeta->link as $picture) {
			$code = (string) $picture->attributes()->residref;
			if(strstr($code, 'picture')) {
				$news['picture'][] = $code;
			}
		}
		
		return $news;
	}
	
	private function get($endpoint, $postfields = [])
	{
		$curl = curl_init($this->base_url.$endpoint);
		curl_setopt($curl, CURLOPT_TIMEOUT, "10");
		if(strstr($endpoint, '/token/')) {
			curl_setopt($curl, CURLOPT_HEADER, 1);
		} else {
			curl_setopt($curl, CURLOPT_HEADER, 0);
		}
		curl_setopt($curl, CURLOPT_USERPWD, $this->username.':'.$this->password);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		if (isset($postfields) && ! empty($postfields)) {
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postfields));
		}
		$curlResult = curl_exec($curl);
		curl_close($curl);
		
		if (strstr($endpoint, '/document/') || strstr($endpoint, '/token/')) {
			return $curlResult;
		}
		
		return json_decode($curlResult, true);
	}
	
	/*
	 * Check subscription status
	 */
	public function subscription(): bool
	{
		$result = $this->get('/subscription');
		
		if ($result['response']['success']) {
			return true;
		}
		
		return false;
	}
	
	public function getCategories(): array
	{
		return $this->get('/discover/tr_TR')['data'];
	}
	
	private function saveNews($category_id) {
		$news_ids = $this->get('/search', [
			'filter_category' => $category_id,
			'filter_type'     => '1',
			'filter_language' => '1',
			'offset'          => '0',
			'limit'           => '20',
		]);
		
		foreach ($news_ids['data']['result'] as $news_id) {
			$result = $this->get('/document/'.$news_id['id'].'/newsml29');
			if ( ! empty($result)) {
				file_put_contents(__DIR__.'/news/'.$news_id['id'].'.xml', $result);
			}
		}
		
		return array_column($news_ids['data']['result'], 'id');
	}
	
	public function getNews($category_id)
	{
		$news_ids = $this->saveNews($category_id);
		
		foreach($news_ids as $news_id) {
			$news[] = $this->newsmlToArray(file_get_contents(__DIR__.'/news/'.$news_id.'.xml'));
		}
		
		return $news;
	}
	
	public function getDownloadURL($code) {
		$result = $this->get('/token/' . $code . '/web');
		preg_match('#Location: (.*)#i', $result, $url);
		return trim($url[1]) ?? false;
	}
}
