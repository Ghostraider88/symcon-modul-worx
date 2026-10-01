# Worx Mower

The Mower instance is created by the Worx Configurator and uses the selected device from its connected Worx Cloud instance.

## Status and commands

Status and error codes remain numeric variables. Adjacent text variables provide readable labels and can be archived independently in Symcon. Commands show the requested action and its response separately from the state subsequently reported by the mower. A successful transport publish is not device confirmation.

## Weekly schedule

The native Symcon weekly event is the single schedule editor. The module creates or updates it only when the mower provides a supported schedule format. Event changes are validated, sent through the available Worx transport, and confirmed by reading the schedule back from the mower. Changes received from the Worx app update the event as well.

Applying instance configuration does not send schedule changes. Unsupported schedule fields are not presented as editable. Keep a copy of the existing schedule before testing changes on a real mower.

## Other settings

Controls are shown only when the mower reports the required capability and field. Requested values and confirmed values use separate variables. The firmware update request remains hidden until an explicit cloud availability check confirms that an update is supported and available; additional online, charging/docked, and battery checks apply. Configuration changes alone never trigger a firmware update.

For readable history in Symcon, enable archiving for the relevant status text variables in Archive Control.