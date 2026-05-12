Set WshShell = CreateObject("WScript.Shell")
WshShell.Run chr(34) & "C:\htdocs\Lakshya\scripts\start_ai_worker.bat" & Chr(34), 0
Set WshShell = Nothing
