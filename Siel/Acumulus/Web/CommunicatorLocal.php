<?php
namespace Siel\Acumulus\Web;

/**
 * CommunicationLocal is a class derived from Communication that can be used for
 * testing purposes. It does not actually send the message to Acumulus and fakes
 * a response.
 */
class CommunicatorLocal extends Communicator
{
    /**
     * @inheritdoc
     */
    protected function sendHttpPost($uri, $post)
    {
        $pluginSettings = $this->config->getPluginSettings();
        if ($pluginSettings['outputFormat'] === 'json') {
            $response = '{
				"errors": {
					"count_errors": "0"
				},
				"warnings": {
					"warning": [ {
						"code": "599",
						"codetag": "LOCAL",
						"message": "Warning - The message has not been sent. The communication layer operates in local debug mode."
					} ],
					"count_warnings": "1"
				},
				"status": "0"
			}';
        } else {
            /** @noinspection HtmlUnknownTag */
            $response = '<myxml>
				<errors>
					<count_errors>0</count_errors>
				</errors>
				<warnings>
					<warning>
						<code>599</code>
						<codetag>LOCAL</codetag>
						<message>Warning - The message has not been sent. The communication layer operates in local debug mode.</message>
					</warning>
				</warnings>
			</myxml>';
        }
        return str_replace(array("\r", "\n", "\t"), '', $response);
    }
}
