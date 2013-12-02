<?php
// Documentação: http://www.mobilepronto.info/samples/pt-BR/1205-httpget-php-v_2_00.pdf

class MobilePronto {
    var $credential; // Credencial do projeto MobilePronto
    var $principal_user; // Até 8 caracteres
    var $aux_user;
    var $mobile;
    var $send_project = 'N'; // S enviar o nome do Projeto - N não enviar o nome do projeto
    var $result_message;
    var $error = false;
    var $url;
    var $return_format = 'json';

    function MobilePronto($credential, $principal_user, $aux_user = '')
    {
        $this->credential = $credential;
        $this->principal_user = substr($principal_user, 0, 8);
        $this->aux_user = $aux_user;
    }

    function get_credits()
    {
        return $this->_call(sprintf("https://www.mpgateway.com/v_2_00/smscredits/credits.aspx?Credencial=%s", $this->credential));
    }

    function send_sms($cellphone, $message)
    {
        $this->send_project = (strtoupper($this->send_project) == 'S' ? 'S' : 'N');
        preg_match('/[0-9]+/', $cellphone, $result);
        $cellphone = $result[0];

        $message = trim($message);
        if ($this->send_project == 'S')
            $message = substr($message, 0, 160-(strlen($this->principal_user) > 0 ? strlen($this->principal_user)+1 : 0));

        $response = $this->_call(sprintf("https://www.mpgateway.com/v_2_00/smspush/enviasms.aspx?CREDENCIAL=%s&PRINCIPAL_USER=%s&AUX_USER=%s&MOBILE=%s&SEND_PROJECT=%s&MESSAGE=%s", $this->credential, $this->principal_user, $this->aux_user, $cellphone, strtoupper($this->send_project), urlencode($message)));
        $this->error = true;
        switch ($response)
        {
            case 'X01':
            case 'X02':
                $this->result_message = 'Parâmetros com Erro.';
                break;
            case '000':
                //$this->result_message = 'Mensagem enviada com Sucesso.';
                $this->error = false;
                return true;
                break;
            case '001':
                $this->result_message = 'Credencial Inválida.';
                break;
            case '005':
                $this->result_message = 'Mobile fora do formato-Formato +999(9999)99999999.';
                break;
            case '009':
                $this->result_message = 'Sem crédito para envio de SMS. Favor repor.';
                break;
            case '010':
                $this->result_message = 'Gateway Bloqueado.';
                break;
            case '012':
                $this->result_message = 'Mobile no formato padrão, mas incorreto.';
                break;
            case '013':
                $this->result_message = 'Mensagem Vazia ou Corpo Inválido.';
                break;
            case '015':
                $this->result_message = 'País sem operação.';
                break;
            case '016':
                $this->result_message = 'Mobile com tamanho do código de área inválido.';
                break;
            case '017':
                $this->result_message = 'Operador não autorizada para esta Credencial.';
                break;
            case '900':
                $this->result_message = 'Erro de autenticação ou Limite de segurança excedido.';
                break;
        }

        if ($response >= 800 && $response <= 899)
            $this->result_message = 'Falha no gateway Mobile Pronto. Contate o suporte Mobile Pronto.';

        if ($response >= 901 && $response <= 999)
            $this->result_message = 'Erro no acesso as operadoras. Contate o suporte Mobile Pronto.';

        if ($response != '000')
            header('HTTP/ 400 Bad Request');

        $this->result_message = $response . ': ' . $this->result_message;

        return $response;
    }

    function _call($url)
    {
        $this->url = $url;
        $response = fopen($this->url, 'r');
        return fgets($response, 4);
    }

    function get_error($output = false) {
        $result = array('message' => $this->result_message, 'url' => $this->url);
        if ($this->return_format == 'json')
            $result = json_encode($result);
        if ($output && $this->return_format == 'json')
            die($result);

        if ($output)
        {
            echo '<pre>';
            print_r($result);
            echo '</pre>';
        }
        return $result;
    }
}

/*


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
Caso o SEND_PROJECT seja N o texto deverá tem até 160 160 caractéres.*/