<?php
	$mensagem = isset($texto[1]) and is_numeric($texto[1]) and $texto[1]>1 ?
		'<b>' . mt_rand(1, $texto[1]) . '</b>' :
		'📚: /' . GERAR[$idioma] . ' ' . mt_rand(1, 100);

	sendMessage($mensagens['message']['chat']['id'], $mensagem, $mensagens['message']['message_id'], null, true);
