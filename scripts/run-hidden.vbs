Option Explicit
Dim shell, files, scriptPath, command
If WScript.Arguments.Count <> 1 Then WScript.Quit 1
Set shell = CreateObject("WScript.Shell")
Set files = CreateObject("Scripting.FileSystemObject")
scriptPath = files.GetAbsolutePathName(WScript.Arguments(0))
If Not files.FileExists(scriptPath) Then WScript.Quit 1
command = """" & shell.ExpandEnvironmentStrings("%SystemRoot%") & "\System32\WindowsPowerShell\v1.0\powershell.exe"" -NoProfile -NonInteractive -ExecutionPolicy Bypass -File """ & scriptPath & """"
' wscript is a GUI host; PowerShell is created hidden before any console can appear.
WScript.Quit shell.Run(command, 0, True)
