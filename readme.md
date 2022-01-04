

# OpenDialog - open-source conversational application platform

OpenDialog enables you to quickly design, develop and deploy conversational applications. 

https://user-images.githubusercontent.com/78927881/148096488-4fdf4b6f-19e8-4a3a-a365-4e9b6bc37ce0.mp4

You write conversational applications using OpenDialog's flexible no-code conversation designer and define the messages that your bot will send the user through OpenDialog's message editor.  

The OpenDialog webchat widget allows you to interact with the application - it supports both an in-page popup experience as well as a full-page experience and mobile. 

<img src="https://www.opendialog.ai/wp-content/uploads/2021/04/webchat_images.png" alt="OpenDialog Webchat Widget">

Behind the scenes this all gets translated into the OpenDialog Conversation Description Language and gets run through our powerful conversation engine, giving you flexible, sophisticated chat experiences everytime. 

For all the details of how OpenDialog helps you build sophisticated conversation applications visit our [documentation site](https://docs.opendialog.ai).


# Trying out OpenDialog

If you want to see OpenDialog in action you can try out the latest version through our automatically produced Docker image.

The [OpenDialog Quick Start Repo](https://github.com/opendialogai/quick-start) contains a `docker-compose.yml` file that will pull in the latest OpenDialog docker image and spin it up alongside all other containers needed to test out OpenDialog

As long as you have Docker installed on your local machine you can do:
- `git clone https://github.com/opendialogai/quick-start.git`
- `cd quick-start`
- `docker-compose up -d app`
- `docker-compose exec app bash docker/scripts/update-docker.sh`

You can then visit http://localhost and login to OpenDialog with admin@example.com / opendialog - you can also view the full page webchat experience on http://localhost/web-chat

There are more detailed instructions in readme the `quick-start` repo

# Learning about OpenDialog

To find out more about how OpenDialog works and a guide to building your first conversational application with OpenDialog visit our [docs website](https://docs.opendialog.ai). 

Read our [OpenDialog Manifesto](https://www.opendialog.ai/manifesto) which captures our views on what is at the core of conversational applications and what the most important design principles are. These ideas underpin our vision for OpenDialog.

# Developing with OpenDialog

To setup a development environment for OpenDialog please check out the [OpenDialog development environment repository](https://github.com/opendialogai/opendialog-dev-environment) - it provides step by step instructions for setting up a Docker-based dev environment.

# Contributing to OpenDialog

We strongly encourage everyone who wants to help the OpenDialog development take a look at the following resources:
- CONTRIBUTING.md
- CODE_OF_CONDUCT.md
- Take a look at our issues

# License

All or parts of this software are licensed as described below.  

The following are licensed under the Apache 2.0 Licence:-

* All content related to the “OpenDialog Conversation Description Language” are publicly available here: https://docs.opendialog.ai/reference/conversation-description-language.
* All content that resides within the "core" repository, asides from "core/src/ConversationEngine", is licensed under the licence defined in "/core/LICENCE".
* All content that resides within the "opendialog" repository is licensed under the licence defined in "opendialog/blob/1.x/LICENSE".
* All content that resides within the "webchat" repository is licensed under the licence defined in "webchat/LICENCE".

The following are licensed under the OpenDialog Enterprise Licence:-

* All content that resides within the "core/src/ConversationEngine" directory of the "core" repository is licensed under the licence defined in "/core/src/ConversationEngine/LICENCE".
* All content that resides under the "opendialog-design-system" repository is licensed under the licence defined in "opendialog-design-system/LICENCE".

All third party components incorporated into the OpenDialog Software are licensed under the original licence provided by the owner or licensee of the applicable component.

Copyright (c) 2021 GreenShoot Labs Ltd. All rights reserved.

