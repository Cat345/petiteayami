/**
 * Type declarations for Divi 5 external dependencies.
 *
 * These modules are loaded at runtime by Divi and are registered as webpack externals.
 * This file tells TypeScript about their existence to prevent red underlines.
 *
 * @since 1.0.0
 */

declare module '@divi/module' {
  export interface ModuleClassnamesParams {
    classnamesInstance: any;
    attrs: any;
  }
  export interface ModuleStylesParams {
    attrs: any;
    elements: any;
    settings: any;
    state: any;
    mode: string;
  }
  export interface ModuleScriptDataParams {
    attrs: any;
    id: string;
    selector: string;
    storeInstance: any;
  }
  export const ModuleContainer: any;
}

declare module '@divi/module-library' {
  export interface ModuleEditProps<T> {
    attrs: T;
    elements: any;
    id: string;
    name: string;
  }
  export const registerModule: any;
}

declare module '@divi/types' {
  export type FormatBreakpointStateAttr<T> = any;
  export type InternalAttrs = any;
  export namespace Metadata {
    export type Values<T> = any;
    export type DefaultAttributes<T> = any;
  }
  export namespace ModuleLibrary {
    export namespace Module {
      export interface RegisterDefinition<T> {
        metadata: any;
        defaultAttrs: any;
        defaultPrintedStyleAttrs: any;
        placeholderContent: T;
        renderers: {
          edit: any;
        };
      }
    }
  }
  export namespace Element {
    export namespace Meta {
      export interface Attributes {
        adminLabel?: any;
      }
    }
    export namespace Advanced {
      export namespace IdClasses {
        export interface Attributes {
          id?: any;
          className?: any;
        }
      }
      export namespace Text {
        export interface Attributes {
          text?: {
            desktop?: {
              value?: {
                align?: string;
                orientation?: string;
              };
            };
          };
        }
      }
    }
    export namespace Decoration {
      export type PickedAttributes<T extends string> = any;
    }
    export namespace Types {
      export namespace Text {
        export namespace InnerContent {
          export interface Attributes {
            desktop?: {
              value?: string | { text?: string };
            };
          }
        }
      }
    }
  }
  export namespace Module {
    export namespace Css {
      export interface AttributeValue {
        [key: string]: any;
      }
    }
  }
}

declare module '@wordpress/hooks' {
  export const addAction: any;
}
