import { Model, DataTypes } from "sequelize";
import { hashSync } from "bcryptjs";
import * as validations from "../validation";
import * as errors from "../validation/errors";
import Avatar from "./Avatar";
import { itExists } from "./../validation/index";
import Photo from "./Photo";

export default class User extends Model {
  static init(sequelize) {
    super.init(
      {
        nickname: {
          type: DataTypes.STRING(20),
          defaultValue: "",
          unique: {
            msg: errors.models.nickname.inUse,
          },
        },
        email: {
          type: DataTypes.STRING,
          unique: true,
          allowNull: false,
        },
        energeticAmount: {
          type: DataTypes.INTEGER,
          defaultValue: 3,
        },
        rubiesAmount: {
          type: DataTypes.INTEGER,
          defaultValue: 0,
        },
        checkpoint: {
          type: DataTypes.INTEGER,
          references: {
            model: "lessons",
            key: "id",
          },
        },
        selectedAvatarId: {
          type: DataTypes.INTEGER,
          defaultValue: 1,
          validate: {
            itExists: async function (value) {
              const avatar = await Avatar.findByPk(value);
              if (!avatar) {
                throw new Error(errors.models.selectedAvatar.nonExistent);
              }
            },
          },
          references: {
            model: Avatar,
            key: "id",
          },
        },
        password: {
          type: DataTypes.STRING,
          defaultValue: "",
        },
      },
      {
        sequelize,
        modelName: "user",
      }
    );
    this.addHook("afterValidate", (user) => {
      user.password = hashSync(user.password, 10);
    });

    this.addScope("defaultScope", {
      attributes: { exclude: ["selectedAvatarId"] },
      include: [
        { model: Avatar, as: "selectedAvatar", attributes: ["id", "url"] },
      ],
    });
    this.addScope("noPassword", {
      attributes: { exclude: ["password"] },
    });
    this.addScope("avatars", {
      include: [
        {
          model: Avatar,
          as: "avatars",
          through: { attributes: [] },
          attributes: ["id", "url"],
        },
      ],
    });
  }

  static associate(models) {
    this.belongsTo(models.avatar, {
      as: "selectedAvatar",
      foreignKey: "selectedAvatarId",
    });
    this.belongsToMany(models.avatar, {
      as: "avatars",
      foreignKey: "userId",
      through: models.userAvatar,
    });
  }
}
