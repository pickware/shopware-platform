import ApiService from '../api.service';

/**
 * Gateway for the API end point "mail"
 * @class
 * @extends ApiService
 * @sw-package framework
 */
class MailApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'mail-template') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'mailService';
    }

    getBasicHeaders(additionalHeaders) {
        const apiContext = {
            ...Shopware.Context.api,
            ...additionalHeaders,
        };

        let languageIdHeader = {};

        // eslint-disable-next-line no-restricted-globals
        if (self?.Shopware && typeof apiContext.languageId === 'string') {
            languageIdHeader = {
                'sw-language-id': apiContext.languageId,
            };
        }

        return super.getBasicHeaders(languageIdHeader);
    }

    sendMailTemplate(
        recipientMail,
        recipient,
        mailTemplate,
        mailTemplateMedia,
        salesChannelId,
        testMode = false,
        documentIds = [],
        templateData = null,
        mailTemplateTypeId = null,
        mailTemplateId = null,
        additionalHeaders = {},
    ) {
        const apiRoute = `/_action/${this.getApiBasePath()}/send`;

        return this.httpClient
            .post(
                apiRoute,
                {
                    contentHtml: mailTemplate.contentHtml ?? mailTemplate.translated?.contentHtml,
                    contentPlain: mailTemplate.contentPlain ?? mailTemplate.translated?.contentPlain,
                    mailTemplateData: templateData ?? mailTemplate.mailTemplateType.templateData,
                    recipients: { [recipientMail]: recipient },
                    salesChannelId: salesChannelId,
                    mediaIds: mailTemplateMedia.getIds(),
                    subject: mailTemplate.subject ?? mailTemplate.translated?.subject,
                    senderMail: mailTemplate.senderMail,
                    senderName: mailTemplate.senderName ?? mailTemplate.translated?.senderName,
                    documentIds,
                    testMode,
                    mailTemplateTypeId,
                    mailTemplateId,
                },
                {
                    headers: this.getBasicHeaders(additionalHeaders),
                },
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }

    testMailTemplate(
        recipient,
        mailTemplate,
        mailTemplateMedia,
        salesChannelId,
        mailTemplateTypeId,
        mailTemplateId,
        documentIds = [],
    ) {
        return this.sendMailTemplate(
            recipient,
            recipient,
            mailTemplate,
            mailTemplateMedia,
            salesChannelId,
            true,
            documentIds,
            null,
            mailTemplateTypeId,
            mailTemplateId,
        );
    }

    buildRenderPreview(mailTemplateType, mailTemplate) {
        const apiRoute = `/_action/${this.getApiBasePath()}/build`;

        return this.httpClient
            .post(
                apiRoute,
                {
                    mailTemplateType: mailTemplateType,
                    mailTemplate: mailTemplate,
                },
                {
                    headers: this.getBasicHeaders(),
                },
            )
            .then((response) => {
                return ApiService.handleResponse(response);
            });
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default MailApiService;
