<?php

/**
 * Classe MobilePronto para envio de SMS
 *
 * Documentação completa disponível em http://www.mobilepronto.info/samples/pt-BR/1205-httpget-php-v_2_00.pdf
 * ======= Como usar =======
 * # Configurar a chave e o nome do Projeto
 * MobilePronto::Setup('chave', 'projeto');
 *
 * # Enviar SMS
 * MobilePronto::sendsms('+5527999999999', 'mensagem');
 *
 *
 * @author Diego Vieira <diego@protos.inf.br>
 * @link http://www.mobilepronto.info/samples/pt-BR/1205-httpget-php-v_2_00.pdf
 * @date 2014-01-10
 * @copyright 2013-2014 Protos Sistemas e Projetos LTDA - http://protos.inf.br
 */
class MobilePronto
{
    protected static $credential;
    protected static $principal_user;
    protected static $aux_user;
    protected static $send_project;
    protected static $mobile;
    protected static $result_message;
    protected static $error = false;
    protected static $url;
    protected static $return_format = 'json';

    /**
     * Configura as credenciais e o nome do projeto.
     *
     * @param string $credential Credencial do projeto MobilePronto. Sempre com 40 posições.
     * @param string $principal_user Nome do seu Projeto no Mobile Pronto. (até 8 caracteres)
     * @param string $aux_user Usuário Auxiliar. Serve para filtrar o relatório referente aos SMS enviados.
     * @param string $send_project Envia ou não o nome do Projeto.
     */
    public static function Setup($credential, $principal_user, $aux_user = '', $send_project = 'N')
    {
        if (isset($credential) && !empty($credential) && isset($principal_user) && !empty($principal_user)) {
            self::$credential = $credential;
            self::$principal_user = substr($principal_user, 0, 8);
            self::$aux_user = $aux_user;
            switch ($send_project) {
                case 'N':
                    self::$send_project = 'N';
                    break;
                default:
                    self::$send_project = 'S';
                    break;
            }
        }
    }

    public static function get_credits()
    {
        return (float)self::_call(sprintf("https://www.mpgateway.com/v_2_00/smscredits/credits.aspx?Credencial=%s", self::$credential));
    }

    /**
     * Envia um SMS.
     *
     * @param $cellphone string Número do celular contendo o código do país, código da área e o número. ex. 55 27 99999 9999
     * @param $smsmessage string Mensagem a ser enviada. Texto livre. Máximo de 160 caracteres. Se o nome do projeto for incluído, máximo de 160 caracteres menos o tamanho do nome do projeto menos 1 caracter devido ao ":" (dois pontos) que é adicionado à mensagem.
     * @param $throwerror bool Mostrar um erro e prevenir que a mensagem seja enviada caso ultrapasse o limite. Mude para false se quiser que a mensagem seja truncada.
     * @return bool|string
     */
    public static function sendsms($cellphone, $smsmessage, $throwerror = true)
    {
        $response = null;

        self::$send_project = (strtoupper(self::$send_project) == 'S' ? 'S' : 'N');
        preg_match('/[0-9]+/', $cellphone, $result);
        $cellphone = $result[0];

        $smsmessage = trim($smsmessage);
        if (self::$send_project == 'S')
            $smsmessage = substr($smsmessage, 0, 160 - (strlen(self::$principal_user) > 0 ? strlen(self::$principal_user) + 1 : 0));

        if ($throwerror && strlen($smsmessage) > 160) {
            header('HTTP/ 400 Bad Request');
            self::$result_message = '999: O tamanho da mensagem excede o limite de 160 caracteres';
        } else {
            $response = '1000';
            if (!empty(self::$credential) && !empty(self::$principal_user))
                $response = self::_call(sprintf("https://www.mpgateway.com/v_2_00/smspush/enviasms.aspx?CREDENCIAL=%s&PRINCIPAL_USER=%s&AUX_USER=%s&MOBILE=%s&SEND_PROJECT=%s&MESSAGE=%s", self::$credential, self::$principal_user, self::$aux_user, $cellphone, strtoupper(self::$send_project), urlencode($smsmessage)));

            self::$error = true;
            switch ($response) {
                case 'X01':
                case 'X02':
                    self::$result_message = 'Parâmetros com Erro.';
                    break;
                case '000':
                    //self::$result_message = 'Mensagem enviada com Sucesso.';
                    self::$error = false;
                    return true;
                    break;
                case '001':
                    self::$result_message = 'Credencial Inválida.';
                    break;
                case '005':
                    self::$result_message = 'Mobile fora do formato-Formato +999(9999)99999999.';
                    break;
                case '009':
                    self::$result_message = 'Sem crédito para envio de SMS. Favor repor.';
                    break;
                case '010':
                    self::$result_message = 'Gateway Bloqueado.';
                    break;
                case '012':
                    self::$result_message = 'Mobile no formato padrão, mas incorreto.';
                    break;
                case '013':
                    self::$result_message = 'Mensagem Vazia ou Corpo Inválido.';
                    break;
                case '015':
                    self::$result_message = 'País sem operação.';
                    break;
                case '016':
                    self::$result_message = 'Mobile com tamanho do código de área inválido.';
                    break;
                case '017':
                    self::$result_message = 'Operador não autorizada para esta Credencial.';
                    break;
                case '900':
                    self::$result_message = 'Erro de autenticação ou Limite de segurança excedido.';
                    break;
                case '1000':
                    self::$result_message = 'Não foram informadas as credenciais nas configurações da aplicação.';
                    break;
            }

            if ($response >= 800 && $response <= 899)
                self::$result_message = 'Falha no gateway Mobile Pronto. Contate o suporte Mobile Pronto.';

            if ($response >= 901 && $response <= 999)
                self::$result_message = 'Erro no acesso as operadoras. Contate o suporte Mobile Pronto.';

            if ($response != '000')
                header('HTTP/ 400 Bad Request');

            self::$result_message = $response . ': ' . self::$result_message;
        }

        return $response;
    }

    protected static function _call($url)
    {
        self::$url = $url;
        $response = fopen(self::$url, 'r');
        return fgets($response, 4);
    }

    public static function get_error($output = false)
    {
        $result = array('message' => self::$result_message, 'url' => self::$url);
        if (self::$return_format == 'json')
            $result = json_encode($result);
        if ($output && self::$return_format == 'json')
            die($result);

        if ($output) {
            echo '<pre>';
            print_r($result);
            echo '</pre>';
        }
        return $result;
    }
}

/** 
	Parâmetros:
	CREDENCIAL = Credencial do projeto MobilePronto
	PRINCIPAL_USER = Nome do seu Projeto no Mobile Pronto
	AUX_USER = Usuário Auxiliar. Serve para filtrar
	MOBILE = Número do celular que receberá o SMS
	SEND_PROJECT = Indica se a mensagem vai ter o FROM ou não
	MESSAGE = É a mensagem a ser enviada para o MOBILE informado


	Formato e domínio dos parâmetros:
	CREDENCIAL = Sempre com 40 posições
	PRINCIPAL_USER = Coloque sempre o nome do seu Projeto no Mobile Pronto
	AUX_USER = Usuário Auxiliar. Serve para filtrar uma consulta aos SMS enviados
	MOBILE = 999(999)99999999 ou 99999999999999
	SEND_PROJECT = Uma posição alfa.
	S (indica que o Cód. d
	ou
	 N (indica que o Cód. do Projeto
	Atenção, pois o From pode
	Exemplo: MP:Mensagem de Teste
	MP: – From com :
	Mensagem de Teste – Corpo da mensagem
	MESSAGE = Texto Livre.
	A quantidade de caracteres varia se utiliza SEND_PROJECT S ou N ( SENDERID ).
	Caso o SEND_PROJECT seja S o texto deverá ter
	caractéres do Código do Projeto contando o caracter de separação o dois pontos “:“
	no PAINEL -> MEU ESPAÇO ao lado direito CONFIGURAÇÕES clique em EDITAR Cód. Do
	Caso o SEND_PROJECT seja N o texto deverá tem até 160 160 caractéres.
*/