/*
www.IQB.hu-berlin.de
Bărbulescu, Stroescu, Mechtel
2019
license => MIT

unit authoring tool for IQBSurveyPlayerV1

simple implementation of the Vera Online Interface for unitAuthoring
*/

const player = "IQBSurveyPlayerV3";
const textAreaID = "unitAuthoringTextArea";

const containerWindow = window.parent;
const checkOrigin = false;

// const acceptedOrigin = window.origin;
const acceptedOrigin = '*';
let sessionId = '';


// execute commands after DOM has loaded
document.addEventListener("DOMContentLoaded", () => {
	
	// listen to messages from the host___________________________________________
	window.addEventListener("message", (e) => {
		if ((e.origin === acceptedOrigin) || (!checkOrigin)) {
			if (('type' in e) && ('sessionId' in e.data)) {

				// LoadUnitDefinition
				if (e.data.type === 'vo.ToAuthoringModule.DataTransfer') {
					if ('unitDefinition' in e.data) {
						document.getElementById(textAreaID).value = e.data.unitDefinition;
						sessionId = e.data.sessionId;
					} else {
						console.error('IQB Authoring Tool Error: unitDefinition missing in message LoadUnitDefinition');
					}

				// UnitDefinitionRequest
				} else if (e.data.type === 'vo.ToAuthoringModule.DataRequest') {
					if (e.data.sessionId === sessionId) {

						containerWindow.postMessage({
							'type': "vo.FromAuthoringModule.DataTransfer",
							'sessionId': sessionId,
							'unitDefinition': document.getElementById(textAreaID).value,
							'player': player
						}, acceptedOrigin);
					} else {
						console.error('IQB Authoring Tool Error: authoringSessionId not valid in message UnitDefinitionRequest');
					}
				}
			}
		}
	});

	// listen to changes in the textarea___________________________________________
	document.getElementById(textAreaID).addEventListener('input', (e) => {
		containerWindow.postMessage({
			'type': 'vo.FromAuthoringModule.ChangedNotification',
			'sessionId': sessionId
		}, acceptedOrigin); 
	});

	// sending Ready-message to the host___________________________________________
	containerWindow.postMessage({
		'type': 'vo.FromAuthoringModule.ReadyNotification',
		'version': 1
	}, acceptedOrigin);
})
