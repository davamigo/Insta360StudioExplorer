# Insta360 Studio Explorer

Simple acript utility to scan Insta360 Studio projects, remove projectos and copy the properties from one project to another

## Tech stach

The script has been developed and needs PHP 8.2 to run.


## Running the script

```
php src\main.php [OPTIONS] [PATH]

Options:
        -h, --help              Show this help.
        -o, --ok                Show only projects ok.
        -k, --ko                Show only projects not found.
        -f FILTER               Filter projects.
        -d TARGET               Delete project (by project ID).
        -c SOURCE TARGET        Copy project properties (by project ID).
```

### Showing the projects

```
php src\main.php C:\Users\User\Documents\Insta360\Studio\Project
```

Shows all the projects in the Insta36 project folder


```
php src\main.php . -o
```

Shows only the projects ttah the video files are present in the current folder.


```
php src\main.php . -k
```

Shows only the projects ttah the video files are missing in the current folder.


```
php src\main.php -f "VID_20220619_081138"
```

Shows the project that matches the filter.
It searches for partial coincidences in the `projectId`, the video folder and the file names.
The Insta3360 project folder is assumed to be the current folder.


### Removing a project

```
php src\main.php -d d428f2a7e54781b06eb79797ba2a64b1
```

Delete the project with id=d428f2a7e54781b06eb79797ba2a64b1 completely.
This action cannot be undone.
It is recommended to make a backup before trying.
No responsibility is assumed for any damage that may be caused to Insta360 projects.


### Copying the atributes from one projecto to another

```
php src\main.php -c 5d77d96ca48675594dbaf5bd987a8895 2f879a7b4eea986605420580d35130e3
```

Copy the attributes from the source proyect id=5d77d96ca48675594dbaf5bd987a8895 to the target project id=2f879a7b4eea986605420580d35130e3.
This action cannot be undone.
It is recommended to make a backup before trying.
No responsibility is assumed for any damage that may be caused to Insta360 projects.


## Disclaimer

Use this tool under your own responsibility.
It is recommended to make a backup before using thiis script.
No responsibility is assumed for any damage that may be caused to Insta360 projects.


## Attributions

I don't have any relation with [**Insta360**](https://www.insta360.com/) or with **Arashi Vision Inc.** company.

[**Insta360 Studio 2022**](https://www.insta360.com/support/supportcourse?post_id=18191) is a desktop editing software for windows that allows the owners of Insta360 cameras to edit videos. As they say [on their website](https://www.insta360.com/download/insta360-oners):

> **Insta360 Studio 2022** allows users to edit videos and photos shot on ONE RS/R, ONE X2/X, GO 2, Sphere, EVO, GO, ONE, Nano S, Nano and Air. It contains the Insta360 Plugin for Adobe Premiere Pro(2019/2020/2021) and Final Cut Pro X (only for ONE R wide-angle files) which enable you to open and edit mp4 files in Premiere/Final Cut Pro X.


## Development

I did this software to fix a problem I had with **Insta360 Studio 2022**: Every time I renamed or moved the `.insv` video files to another folder, I lost any previous editing I had done. That was very annoying for me, so I did some research and found out that the old projects were not lost; they are in a subfolder, inside the user's documents folder `C:\Users\User\Documents\Insta360\Studio\Project`. In this projects folder there are one subforder for each of the Insta380 projects. The name of the project subfolder seems to be an hexadecimal hash generated from the full path of the video file name. I call that hash `projectId`. So without knowing the hash function to generate the `projectId` is imposible to create new projects. However you can edit existing projects.

There are several files and subfolders inside the project subfolder:

* The **project file** with `.insprj` extension (eg. `VID_20220816_112725_00_001.insv.insprj`). This file is a simple XML, so it can be edited with any text editor.

* A folder called **`thumbnail`** containing many jpg files.
  
* Optionally a file called **`deeptrack.db`** (nmo está en todos los proyectos).

