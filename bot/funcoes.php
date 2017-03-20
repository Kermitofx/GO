<?php
	function conectarRedis() {
		$redis = new Redis();
	  $redis->connect('127.0.0.1', '6379');
		$redis->select(4);

		return $redis;
	}

	/**
	 * @param string $requisicao
	 */
	function enviarRequisicao($requisicao, $conteudoRequisicao = []) {
		$handle = curl_init($requisicao);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($handle, CURLOPT_TIMEOUT, 60);
		curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($conteudoRequisicao));
		curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

		$resultado = curl_exec($handle);

		if ($resultado === false) {
	    $errno = curl_errno($handle);
	    $error = curl_error($handle);

	    enviarLog('Curl retornou o erro <b>' . $errno . ':</b> <i>' . $error . '</i>');

	    curl_close($handle);
	  }

	  $httpCodigo = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));

	  curl_close($handle);

	  if ($httpCodigo >= 500) {
	    sleep(10);
	  } else if ($httpCodigo != 200) {
	    enviarLog($requisicao . json_encode($conteudoRequisicao) . "\n\n" . '<pre>' . $resultado . '</pre>');

	    if ($httpCodigo == 401) {
	      throw new Exception('Token de acesso fornecido é inválido!');
	    }
	  }

		return $resultado;
	}

	/**
	 * @param string $mensagem
	 */
	function enviarLog($mensagem) {
		if (GRUPO_LOG !== null) {
			sendMessage(GRUPO_LOG, $mensagem, null, null, true);

			return true;
		}

		return notificarSudos($mensagem);
	}

	/**
	 * @param string $mensagem
	 */
	function notificarSudos($mensagem) {
		foreach (SUDOS as $sudo) {
			sendMessage($sudo, $mensagem, null, null, true);

			return true;
		}

		return false;
	}

	function atualizarMensagens($resultado) {
		$updateID = 0;

		foreach ($resultado as $mensagens) {
			if (isset($mensagens['message']['date']) and time() - $mensagens['message']['date']<=5) {
				getUpdates($updateID);

				return null;
			}

			$updateID = $mensagens['update_id'];
		}

		++$updateID;

		return getUpdates($updateID);
	}

	function firstUpdate() {
		$loop = true;
		$requisicao = API_BOT . '/getUpdates';
		$conteudoRequisicao = ['allowed_updates' => ['message', 'edited_message', 'channel_post', 'callback_query']];
		$resultado = json_decode(enviarRequisicao($requisicao, $conteudoRequisicao), true);

		while ($loop === true) {
			$resultado = !empty($resultado['result']) ? atualizarMensagens($resultado['result']) : $loop = false;
		}

		return notificarSudos('<pre>Iniciando...</pre>');
	}

	/**
	 * @param string $arquivo
	 */
	 function carregarDados($arquivo) {
		 if (file_exists($arquivo)) {
			 return json_decode(file_get_contents($arquivo), true);
		 }

		 return null;
	 }

	/**
	 * @param string $arquivo
	 * @param string $dados
	 */
	function salvarDados($arquivo, $dados) {
		return file_put_contents($arquivo, json_encode($dados));
	}

	function mensagemRSS($conteudoRSS) {
		$mensagem = '〰〰〰〰〰〰〰' . "\n\n";

		foreach ($conteudoRSS as $item) {
			$item->title = html_entity_decode(strip_tags($item->title), ENT_QUOTES, 'UTF-8');
			$mensagem = $mensagem . '<a href="' . $item->link . '">' . $item->title . '</a>' . "\n\n";
			$mensagem = $mensagem . html_entity_decode(strip_tags($item->description), ENT_QUOTES, 'UTF-8');

			break;
		}

		return $mensagem . "\n\n" . '〰〰〰〰〰〰〰';
	}

	function removerComando($comando, $mensagem) {
		return str_ireplace('/' . $comando . ' ', '', $mensagem);
	}

	function validarAdmin($resultado, $usuarioID) {
		foreach ($resultado as $adminsGrupo) {
			if ($adminsGrupo['user']['id'] == $usuarioID) {
				return true;
			}
		}

		return false;
	}

	function montarTeclado($conteudo) {
		$teclado = null;

		$posicao1 = stripos($conteudo, '[');

		if ($posicao1 !== false) {
			$montarTeclado = substr($conteudo, $posicao1 + 1);
			$posicao2 = strripos($montarTeclado, ']');
			$montarTeclado = str_ireplace(substr($montarTeclado, $posicao2), '', $montarTeclado);

			$cont = 0;
			$linha = 0;

			$botoes = array_filter(explode('[', $montarTeclado));

			foreach ($botoes as $botao) {
				$botao = str_ireplace(']', '', $botao);

				if ($cont%2 == 0) {
					$teclado['inline_keyboard'][$linha][0]['text'] = $botao;
				} else if ($cont%2 == 1) {
					$tipoBotao = !filter_var($botao, FILTER_VALIDATE_URL) === false ? 'url' : 'callback_data';

					$teclado['inline_keyboard'][$linha][0][$tipoBotao] = $botao;

					++$linha;
				}

				++$cont;
			}

			return json_encode($teclado);
		}

		return null;
	}

	function removerTeclado($conteudo) {
		$posicao = stripos($conteudo, '[');
		$teclado = substr($conteudo, $posicao + 1);

		return str_ireplace('[' . $teclado, '', $conteudo);
	}

	function pingServidor($servidor, $porta = 80) {
		$inicioTempo = microtime(true);
		$resultado = @fsockopen($servidor, $porta, $errno, $errstr, 10);
		$fimTempo = microtime(true);

		if ($resultado === false) {
			return -1;
		}

		fclose($resultado);

		return floor(($fimTempo - $inicioTempo)*1000);
	}

	function manipularErros($erroCodigo = null, $erroMensagem = null, $erroArquivo = null, $erroLinha = null) {
    if (error_reporting() == 0) {
      return null;
    }

    if (func_num_args() == 5) {
      list($erroCodigo, $erroMensagem, $erroArquivo, $erroLinha) = func_get_args();
    } else if (func_num_args() != 5) {
			$excecao = func_get_arg(0);
			$erroCodigo = $excecao->getCode();
			$erroMensagem = $excecao->getMessage();
			$erroArquivo = $excecao->getFile();
			$erroLinha = $excecao->getLine();
    }

    $erroTipo = array(
      E_COMPILE_ERROR => 'COMPILE ERROR', E_COMPILE_WARNING => 'COMPILE WARNING', E_CORE_ERROR => 'CORE ERROR',
			E_CORE_WARNING => 'CORE WARNING', E_ERROR => 'ERROR', E_NOTICE => 'NOTICE', E_PARSE => 'PARSING ERROR',
			E_RECOVERABLE_ERROR => 'RECOVERABLE ERROR', E_STRICT => 'STRICT NOTICE', E_USER_ERROR => 'USER ERROR',
			E_USER_NOTICE => 'USER NOTICE', E_USER_WARNING => 'USER WARNING', E_WARNING => 'WARNING'
  	);

		array_key_exists($erroCodigo, $erroTipo) ? $erroEncontrado = $erroTipo[$erroCodigo] : $erroEncontrado = 'CAUGHT EXCEPTION';

    $mensagem = '<pre>🐞 ERRO ENCONTRADO</pre>' . "\n\n";
		$mensagem .= '<b>Tipo:</b> ' . $erroEncontrado . "\n";
    $mensagem .= '<b>Arquivo:</b> ' . $erroArquivo . "\n";
		$mensagem .= '<b>Linha:</b> ' . $erroLinha . "\n";
    $mensagem .= '<b>Descrição:</b> ' . $erroMensagem . "\n";
		$mensagem .= '<b>Data e Hora:</b> ' . date('d/m/Y H:i:s') . "\n";

    echo '🐞  ERRO: ' , $erroMensagem , ' no arquivo ' , $erroArquivo , ' (Linha ' , $erroLinha , ')' , "\n\n";

		enviarLog($mensagem);
  }

	set_error_handler('manipularErros');
