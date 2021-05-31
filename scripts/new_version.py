#!/usr/bin/python3
"""This script shall be used to create a new release.

It consists of several steps:

STEP 1 - Update version:
A parameter has to be passed which can be one of the following:
'major'/'minor'/'patch'.
Depending on this parameter the version of the software will be updated in the
corresponding file. The file which holds this information can be set at the top
of the script with the VERSION_FILE variable and the regular expression to find
the string can be set as well. The Regex uses Capture Groups and the first one
is taken.

STEP 2 - Run Tests:
Starting the software stack via docker and run the tests.
Thise tests may change additional files, which have to be commited as well.

STEP 3 - Git commit and tag
Several steps are taken to update the git repository. The shell commands are the following:
    git add {VERSION_FILE}
    git commit -m "Update version to {new_version}"
    git push
    git tag {new_version}
    git push origin {new_version}
"""
import sys
import re
import subprocess

VERSION_FILE = 'VERSION'
VERSION_REGEX = '(.*)[\n\r]'
ADDITIONAL_FILES_TO_COMMIT = []


def check_git_status():
    # on branch master?
    result = subprocess.run("git rev-parse --abbrev-ref HEAD",
                            text=True, shell=True, check=True, capture_output=True)
    if result.stdout.rstrip() != 'master':
        sys.exit('ERROR: Not on master branch!')
    # pulled?
    result = subprocess.run("git fetch origin --dry-run",
                            text=True, shell=True, check=True, capture_output=True)
    if result.stderr.rstrip() != '':
        sys.exit('ERROR: Not up to date with remote branch!')


def parse_version_from_file() -> str:
    pattern = re.compile(VERSION_REGEX)
    with open(VERSION_FILE) as version_file:
        file_content = version_file.read()
    match = pattern.search(file_content)
    if match:
        return match.group(1)
    else:
        sys.exit('Version pattern not found in file. Check your regex!')


def update_version_in_file(version):
    pattern = re.compile(VERSION_REGEX)
    with open(VERSION_FILE) as version_file:
        file_content = version_file.read()
    new_file_content = pattern.sub(version, file_content)
    with open(VERSION_FILE, 'w') as f:
        f.write(new_file_content)


def increment_version(old) -> str:
    version_part = sys.argv[1]
    old_version_as_list = old.split('.')
    if version_part == 'major':
        new = f'{int(old_version_as_list[0]) + 1}.0.0'
    elif version_part == 'minor':
        new = f'{old_version_as_list[0]}.{int(old_version_as_list[1]) + 1}.0'
    else:
        new = f'{old_version_as_list[0]}.{old_version_as_list[1]}.{int(old_version_as_list[2]) + 1}'
    return new


def run_software():
    subprocess.run('make run-detached', shell=True, check=True)


def stop_software():
    subprocess.run('make stop', shell=True, check=True)


def run_tests():
    subprocess.run('make test', shell=True, check=True)


def git_tag(version: str):
    print(f"Creating git tag for version {version}")
    subprocess.run(f"git add {VERSION_FILE}", shell=True, check=True)
    for file in ADDITIONAL_FILES_TO_COMMIT:
        subprocess.run(f"git add {file}", shell=True, check=True)
    subprocess.run(f"git commit -m \"Update version to {version}\"", shell=True, check=True)
    subprocess.run("git push origin master", shell=True, check=True)
    subprocess.run(f"git tag {version}", shell=True, check=True)
    subprocess.run(f"git push origin {version}", shell=True, check=True)


def undo_version_update_in_files():
    subprocess.run(f"git checkout {VERSION_FILE}", shell=True, check=True)
    for file in ADDITIONAL_FILES_TO_COMMIT:
        subprocess.run(f"git checkout {file}", shell=True, check=True)


########################
# WORKFLOW STARTS HERE #
########################
if len(sys.argv) < 2:
    sys.exit('ERROR: No parameter given. Use major/minor/patch!')

if not sys.argv[1] in ['major', 'minor', 'patch']:
    sys.exit('Wrong parameter. Use major/minor/patch')

check_git_status()
old_version = parse_version_from_file()
new_version = increment_version(old_version)

try:
    run_software()
    run_tests()
    stop_software()
    update_version_in_file(new_version)
    git_tag(new_version)
except subprocess.SubprocessError:
    stop_software()
    undo_version_update_in_files()
